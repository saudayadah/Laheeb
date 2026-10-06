import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';

export interface NavSection {
    label?: string;
    items: NavItem[];
}

export function NavMain({ sections = [] }: { sections: NavSection[] }) {
    const page = usePage();

    const isActive = (url: string) => page.url === url || page.url.startsWith(`${url}/`) || page.url.startsWith(`${url}?`);

    return (
        <>
            {sections
                .filter((section) => section.items.length > 0)
                .map((section, index) => (
                    <SidebarGroup key={section.label ?? index} className="px-2 py-0">
                        {section.label && <SidebarGroupLabel>{section.label}</SidebarGroupLabel>}
                        <SidebarMenu>
                            {section.items.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton asChild isActive={isActive(item.url)}>
                                        <Link href={item.url} prefetch>
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                ))}
        </>
    );
}
