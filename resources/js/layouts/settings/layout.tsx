import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        url: '/settings/profile',
        icon: null,
    },
    {
        title: 'Password',
        url: '/settings/password',
        icon: null,
    },
    {
        title: 'Appearance',
        url: '/settings/appearance',
        icon: null,
    },
];

export default function SettingsLayout({ children }: { children: React.ReactNode }) {
    const currentPath = window.location.pathname;
    const { schoolSettingsNav, accountSettingsNav, schoolYearSettingsNav, sectionSettingsNav, auditLogNav } = usePage<SharedData>().props;
    const items = schoolSettingsNav.visible
        ? [...sidebarNavItems, { title: schoolSettingsNav.label, url: '/settings/school', icon: null }]
        : sidebarNavItems;
    const accountItems = accountSettingsNav.visible ? [...items, { title: accountSettingsNav.label, url: '/settings/accounts', icon: null }] : items;
    const yearItems = schoolYearSettingsNav.visible
        ? [...accountItems, { title: schoolYearSettingsNav.label, url: '/settings/school-years', icon: null }]
        : accountItems;
    const sectionItems = sectionSettingsNav.visible
        ? [...yearItems, { title: sectionSettingsNav.label, url: '/settings/sections', icon: null }]
        : yearItems;

    const navItems = auditLogNav.visible ? [...sectionItems, { title: auditLogNav.label, url: '/settings/audit-log', icon: null }] : sectionItems;

    return (
        <div className="px-4 py-6">
            <Heading title="Settings" description="Manage your profile and account settings" />

            <div className="flex flex-col space-y-8 lg:flex-row lg:space-y-0 lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav className="flex flex-col space-y-1 space-x-0">
                        {navItems.map((item) => (
                            <Button
                                key={item.url}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': currentPath === item.url,
                                })}
                            >
                                <Link href={item.url} prefetch={item.url !== '/settings/audit-log'}>
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 md:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">{children}</section>
                </div>
            </div>
        </div>
    );
}
