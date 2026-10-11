import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { CalendarDays, ChartNoAxesCombined, ClipboardList, FilePenLine, Folder, LayoutGrid, PieChart, School, Settings, Users } from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { workspace } = usePage<SharedData>().props;
    const t = workspace.text;
    const mainNavItems: NavItem[] = [{ title: t.overview, url: '/dashboard', icon: LayoutGrid }];
    if (workspace.canViewLearners) mainNavItems.push({ title: t.learners, url: '/learners', icon: Users });
    const plannedItems = [
        { title: t.iep, icon: ClipboardList },
        { title: t.intake, icon: FilePenLine },
        { title: t.progress, icon: ChartNoAxesCombined },
        { title: t.schedule, icon: CalendarDays },
    ];
    return (
        <Sidebar collapsible="icon" variant="sidebar" className="border-r">
            <SidebarHeader className="gap-6 px-4 pt-5 pb-6 group-data-[collapsible=icon]:px-1">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <div className="bg-sidebar rounded-xl border p-3 group-data-[collapsible=icon]:hidden">
                    <div className="flex items-start gap-2.5">
                        <School className="text-muted-foreground mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        <div className="min-w-0">
                            <p className="truncate text-xs font-semibold" title={workspace.schoolName ?? t.no_school}>
                                {workspace.schoolName ?? t.no_school}
                            </p>
                            <p className="text-muted-foreground mt-1 truncate text-[11px]">{workspace.schoolYear ?? t.no_year}</p>
                        </div>
                    </div>
                </div>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} label={t.management} />
                {workspace.canViewLearners && (
                    <>
                        <SidebarGroup className="px-2 pt-0">
                            <SidebarMenu>
                                {plannedItems.map(({ title, icon: Icon }) => (
                                    <SidebarMenuItem key={title}>
                                        <SidebarMenuButton disabled className="h-11 rounded-xl" title={t.unavailable}>
                                            <Icon aria-hidden="true" />
                                            <span>{title}</span>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                ))}
                            </SidebarMenu>
                        </SidebarGroup>
                        <SidebarGroup>
                            <SidebarGroupLabel className="text-[10px] tracking-wide uppercase">{t.reports}</SidebarGroupLabel>
                            <SidebarMenu>
                                {[
                                    { title: t.reports, icon: PieChart },
                                    { title: t.resources, icon: Folder },
                                ].map(({ title, icon: Icon }) => (
                                    <SidebarMenuItem key={title}>
                                        <SidebarMenuButton disabled className="h-11 rounded-xl" title={t.unavailable}>
                                            <Icon aria-hidden="true" />
                                            <span>{title}</span>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                ))}
                            </SidebarMenu>
                        </SidebarGroup>
                    </>
                )}
            </SidebarContent>

            <SidebarFooter className="gap-3 px-3 pb-4 group-data-[collapsible=icon]:px-1">
                <NavMain items={[{ title: t.settings, url: '/settings/profile', icon: Settings }]} />
                <div className="border-t" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
