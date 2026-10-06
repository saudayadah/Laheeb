import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: AuthUser | null;
}

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at?: string | null;
    roles: string[];
    permissions: string[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    locale: 'ar' | 'en';
    translations: Record<string, string>;
    auth: Auth;
    flash: { success?: string | null; error?: string | null };
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
    current_page: number;
    last_page: number;
}

export interface IdName {
    id: number;
    name: string;
}

export interface CustomerListItem {
    id: number;
    code: string;
    name: string;
    group: IdName | null;
    route: IdName | null;
    type: string;
    payment_term: string;
    phone: string | null;
    city: string | null;
    active: boolean;
    default_price: string | null;
}

export interface CustomerFormData {
    id?: number;
    code: string | null;
    name: string;
    name_en: string | null;
    customer_group_id: number | null;
    type: string;
    payment_term: string;
    credit_limit: string | null;
    credit_days: number | null;
    delivery_route_id: number | null;
    stop_sequence: number;
    city: string | null;
    phone: string | null;
    whatsapp: string | null;
    map_url: string | null;
    vat_number: string | null;
    cr_number: string | null;
    national_address: string | null;
    active: boolean;
    notes: string | null;
}

export interface PriceRow {
    id: number;
    product: { id: number; name_ar: string; name_en: string | null } | null;
    price: string;
    effective_from: string;
}

export interface ProductItem {
    id: number;
    name_ar: string;
    name_en: string | null;
    category: string;
    size_cm: number | null;
    unit: string;
    default_price: string;
    vat_rate: string | null;
    active: boolean;
    sort_order: number;
}

export interface GroupItem {
    id: number;
    name: string;
    credit_limit: string | null;
    credit_scope: string;
    customers_count: number;
    active: boolean;
    notes: string | null;
}

export interface RouteItem {
    id: number;
    name: string;
    city: string | null;
    default_driver: IdName | null;
    customers_count: number;
    active: boolean;
    sort_order: number;
}

export interface UserItem {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: string | null;
    active: boolean;
}

export interface ImportRow {
    row: number;
    data: Record<string, string | null>;
    errors: string[];
}
