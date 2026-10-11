import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
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
    workspace: {
        schoolName: string | null;
        schoolYear: string | null;
        canViewLearners: boolean;
        text: Record<string, string>;
    };
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    schoolSettingsNav: { visible: boolean; label: string };
    accountSettingsNav: { visible: boolean; label: string };
    schoolYearSettingsNav: { visible: boolean; label: string };
    auditLogNav: { visible: boolean; label: string };
    sectionSettingsNav: { visible: boolean; label: string };
    [key: string]: unknown;
}

export interface User {
    id: string;
    school_id: number | null;
    is_active: boolean;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
