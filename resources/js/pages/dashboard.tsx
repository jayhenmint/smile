import { Button } from '@/components/ui/button';
import { WorkspaceCard, WorkspaceEmpty, WorkspaceStat } from '@/components/workspace-ui';
import AppLayout from '@/layouts/app-layout';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    ChartNoAxesCombined,
    ClipboardCheck,
    ClipboardList,
    Clock3,
    HeartHandshake,
    ShieldCheck,
    Sparkles,
    Users,
} from 'lucide-react';

export default function Dashboard() {
    const { workspace, auth } = usePage<SharedData>().props;
    const t = workspace.text;
    return (
        <AppLayout breadcrumbs={[{ title: t.overview, href: '/dashboard' }]}>
            <Head title={t.overview} />
            <div className="mx-auto flex w-full max-w-[1440px] flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            {t.greeting.replace(':name', auth.user.name.split(' ')[0])}
                        </h1>
                        <p className="text-muted-foreground mt-2 text-sm">{t.subtitle}</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <span className="bg-card flex items-center gap-2 rounded-xl border px-4 py-2.5 text-xs font-medium">
                            <CalendarDays className="text-muted-foreground size-4" aria-hidden="true" />
                            {workspace.schoolYear ?? t.no_year}
                        </span>
                        {workspace.canViewLearners && (
                            <Button asChild className="rounded-xl">
                                <Link href="/learners">
                                    {t.open_learners}
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>
                <div className="bg-primary/8 text-primary flex items-center gap-3 rounded-xl px-5 py-4 text-sm">
                    <ClipboardCheck className="size-5 shrink-0" aria-hidden="true" />
                    <p>
                        <span className="font-medium">{t.banner}.</span> {t.banner_detail}
                    </p>
                </div>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <WorkspaceStat label={t.supported} detail={t.awaiting_records} icon={Users} />
                    <WorkspaceStat label={t.plans} detail={t.awaiting_plans} icon={ClipboardList} />
                    <WorkspaceStat label={t.goals} detail={t.awaiting_progress} icon={ChartNoAxesCombined} tone="text-teal-600 dark:text-teal-400" />
                    <WorkspaceStat label={t.reviews} detail={t.awaiting_reviews} icon={Clock3} tone="text-amber-700 dark:text-amber-400" />
                </div>
                <div className="grid gap-6 xl:grid-cols-[1.75fr_1fr]">
                    <WorkspaceCard>
                        <h2 className="text-base font-medium">{t.progress_title}</h2>
                        <p className="text-muted-foreground mt-1 text-xs">{t.progress_subtitle}</p>
                        <div className="bg-background mt-5 rounded-xl border border-dashed">
                            <WorkspaceEmpty icon={ChartNoAxesCombined} title={t.progress_empty} detail={t.progress_detail} />
                        </div>
                    </WorkspaceCard>
                    <WorkspaceCard>
                        <h2 className="text-base font-medium">{t.health_title}</h2>
                        <div className="flex flex-wrap items-center justify-center gap-6 py-8">
                            <div className="border-muted flex size-32 flex-col items-center justify-center rounded-full border-[14px]">
                                <span className="text-2xl font-semibold">—</span>
                                <span className="text-muted-foreground text-[10px]">{t.learners}</span>
                            </div>
                            <ul className="text-muted-foreground space-y-3 text-xs">
                                {[t.active, t.draft, t.not_started].map((label) => (
                                    <li key={label} className="flex min-w-24 items-center gap-2">
                                        <span className="bg-primary/60 size-2 rounded-full" />
                                        <span className="flex-1">{label}</span>
                                        <span>—</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                        <p className="text-muted-foreground border-t pt-4 text-xs leading-6">{t.health_detail}</p>
                    </WorkspaceCard>
                    <WorkspaceCard>
                        <h2 className="text-base font-medium">{t.activity}</h2>
                        <WorkspaceEmpty icon={HeartHandshake} title={t.activity_empty} detail={t.activity_detail} />
                        <p className="text-muted-foreground flex items-center gap-2 border-t pt-4 text-[11px]">
                            <ShieldCheck className="size-4 shrink-0" aria-hidden="true" />
                            {t.privacy}
                        </p>
                    </WorkspaceCard>
                    <WorkspaceCard>
                        <h2 className="text-base font-medium">{t.upcoming}</h2>
                        <WorkspaceEmpty icon={Sparkles} title={t.upcoming_empty} detail={t.upcoming_detail} />
                    </WorkspaceCard>
                </div>
            </div>
        </AppLayout>
    );
}
