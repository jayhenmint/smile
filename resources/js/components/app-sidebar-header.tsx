import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/hooks/use-appearance';
import { useInitials } from '@/hooks/use-initials';
import { type BreadcrumbItem as BreadcrumbItemType, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Bell, Moon, Search, Sun } from 'lucide-react';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItemType[] }) {
    const { workspace, auth } = usePage<SharedData>().props;
    const t = workspace.text;
    const { appearance, updateAppearance } = useAppearance();
    const initials = useInitials();
    return (
        <header className="bg-card flex h-20 shrink-0 items-center justify-between gap-3 border-b px-4 md:px-8">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="flex items-center gap-2 sm:gap-4">
                {workspace.canViewLearners && (
                    <Link
                        href="/learners"
                        className="bg-background text-muted-foreground hidden h-10 items-center gap-2 rounded-xl border px-3 text-xs lg:flex"
                    >
                        <Search className="size-4" aria-hidden="true" />
                        {t.search}
                    </Link>
                )}
                <div className="bg-background hidden rounded-full border p-1 sm:flex">
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8 rounded-full"
                        aria-label={t.light}
                        aria-pressed={appearance === 'light'}
                        onClick={() => updateAppearance('light')}
                    >
                        <Sun className="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-8 rounded-full"
                        aria-label={t.dark}
                        aria-pressed={appearance === 'dark'}
                        onClick={() => updateAppearance('dark')}
                    >
                        <Moon className="size-4" />
                    </Button>
                </div>
                <Button variant="ghost" size="icon" disabled aria-label={t.notifications} title={t.notifications} className="hidden sm:flex">
                    <Bell className="size-5" />
                </Button>
                <Link
                    href="/settings/profile"
                    aria-label={t.profile}
                    className="bg-accent text-accent-foreground flex size-10 items-center justify-center rounded-full text-xs font-medium"
                >
                    {initials(auth.user.name)}
                </Link>
            </div>
        </header>
    );
}
