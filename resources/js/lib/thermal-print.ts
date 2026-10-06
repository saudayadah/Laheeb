import html2canvas from 'html2canvas';

/**
 * Bluetooth thermal printing (80mm, 203dpi -> 576 dots per line).
 *
 * The receipt DOM is rendered to a canvas and sent as an ESC/POS raster
 * image (GS v 0), so Arabic shaping is pixel-perfect regardless of the
 * printer's firmware. Works over Web Bluetooth (Chrome on Android).
 */

const WIDTH_DOTS = 576;
const BYTES_PER_ROW = WIDTH_DOTS / 8;
const BAND_ROWS = 96; // some printers choke on tall single raster blocks
const CHUNK_BYTES = 160; // BLE write size kept under common 182-byte MTU

// Services seen on common BLE receipt printers.
const CANDIDATE_SERVICES: (number | string)[] = [0x18f0, 'e7810a71-73ae-499d-8c15-faa9aef0c3f2', 0xffe0, 0xff00, 0xfee7];

export function bluetoothAvailable(): boolean {
    return typeof navigator !== 'undefined' && 'bluetooth' in navigator;
}

export async function printElementViaBluetooth(element: HTMLElement, onStatus: (step: string) => void): Promise<void> {
    if (!bluetoothAvailable()) {
        throw new Error('bluetooth-unsupported');
    }

    onStatus('connect');
    /* eslint-disable @typescript-eslint/no-explicit-any */
    const bluetooth = (navigator as any).bluetooth;
    const device = await bluetooth.requestDevice({
        acceptAllDevices: true,
        optionalServices: CANDIDATE_SERVICES,
    });

    const server = await device.gatt.connect();

    try {
        const characteristic = await findWritable(server);
        if (!characteristic) {
            throw new Error('no-writable-characteristic');
        }

        onStatus('render');
        const canvas = await html2canvas(element, { backgroundColor: '#ffffff', scale: 2 });
        const payload = toEscposRaster(canvas);

        onStatus('send');
        for (let i = 0; i < payload.length; i += CHUNK_BYTES) {
            const chunk = payload.slice(i, i + CHUNK_BYTES);
            if (characteristic.properties.writeWithoutResponse) {
                await characteristic.writeValueWithoutResponse(chunk);
            } else {
                await characteristic.writeValue(chunk);
            }
            await sleep(12);
        }
    } finally {
        try {
            device.gatt.disconnect();
        } catch {
            // already gone
        }
    }
    /* eslint-enable @typescript-eslint/no-explicit-any */
}

/* eslint-disable @typescript-eslint/no-explicit-any */
async function findWritable(server: any): Promise<any> {
    const services = await server.getPrimaryServices();
    for (const service of services) {
        const characteristics = await service.getCharacteristics();
        for (const characteristic of characteristics) {
            if (characteristic.properties.writeWithoutResponse || characteristic.properties.write) {
                return characteristic;
            }
        }
    }

    return null;
}
/* eslint-enable @typescript-eslint/no-explicit-any */

/** Resample to 576 dots wide, threshold to 1-bit, emit banded GS v 0 blocks. */
function toEscposRaster(source: HTMLCanvasElement): Uint8Array {
    const height = Math.round((source.height / source.width) * WIDTH_DOTS);
    const canvas = document.createElement('canvas');
    canvas.width = WIDTH_DOTS;
    canvas.height = height;

    const ctx = canvas.getContext('2d')!;
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, WIDTH_DOTS, height);
    ctx.drawImage(source, 0, 0, WIDTH_DOTS, height);

    const pixels = ctx.getImageData(0, 0, WIDTH_DOTS, height).data;
    const bytes: number[] = [0x1b, 0x40]; // ESC @ init

    for (let bandTop = 0; bandTop < height; bandTop += BAND_ROWS) {
        const rows = Math.min(BAND_ROWS, height - bandTop);

        // GS v 0 m xL xH yL yH
        bytes.push(0x1d, 0x76, 0x30, 0x00, BYTES_PER_ROW & 0xff, (BYTES_PER_ROW >> 8) & 0xff, rows & 0xff, (rows >> 8) & 0xff);

        for (let y = bandTop; y < bandTop + rows; y++) {
            for (let byteX = 0; byteX < BYTES_PER_ROW; byteX++) {
                let value = 0;
                for (let bit = 0; bit < 8; bit++) {
                    const x = byteX * 8 + bit;
                    const offset = (y * WIDTH_DOTS + x) * 4;
                    const luma = 0.299 * pixels[offset] + 0.587 * pixels[offset + 1] + 0.114 * pixels[offset + 2];
                    const alpha = pixels[offset + 3];
                    if (alpha > 128 && luma < 160) {
                        value |= 0x80 >> bit;
                    }
                }
                bytes.push(value);
            }
        }
    }

    bytes.push(0x1b, 0x64, 0x05); // feed 5 lines so the tear-off clears the print

    return new Uint8Array(bytes);
}

function sleep(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
}
