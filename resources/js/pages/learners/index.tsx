import { Button } from '@/components/ui/button';
import { WorkspaceEmpty } from '@/components/workspace-ui';
import AppLayout from '@/layouts/app-layout';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronLeft, ChevronRight, ClipboardCheck, Clock3, Download, Plus, Search, SlidersHorizontal, Users } from 'lucide-react';

type RosterProps = {
    text: Record<string, string>;
    created: boolean;
    duplicateWarning: boolean;
    learners: {
        data: { id: string; name: string; smile_id: string; lrn: string | null; enrollments: { section: string; year: string }[] }[];
        total: number;
        from: number | null;
        to: number | null;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
};
export default function Learners({ learners, text, created, duplicateWarning }: RosterProps) {
    const { workspace, sectionSettingsNav } = usePage<SharedData>().props;
    const t = workspace.text;
    return (
        <AppLayout breadcrumbs={[{ title: t.learners, href: '/learners' }]}>
            <Head title={t.learners} />
            <div className="mx-auto flex w-full max-w-[1440px] flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl leading-normal font-bold sm:text-[28px]">{t.learners}</h1>
                        <p className="text-muted-foreground mt-2 text-sm">{t.learner_subtitle}</p>
                    </div>
                    <div className="flex gap-3">
                        <Button variant="outline" disabled title={t.unavailable} className="rounded-xl">
                            <Download className="size-4" />
                            {t.export}
                        </Button>
                        <Button asChild className="min-h-11 rounded-xl">
                            <Link href="/learners/create">
                                <Plus className="size-4" />
                                {t.add}
                            </Link>
                        </Button>
                    </div>
                </div>
                {created && (
                    <p role="status" className="rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-800">
                        {text.created}
                    </p>
                )}
                {duplicateWarning && (
                    <p role="alert" className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        {text.duplicate}
                    </p>
                )}
                <div className="bg-card grid gap-5 rounded-2xl border px-5 py-6 shadow-xs sm:grid-cols-3 sm:px-6">
                    {[
                        { label: text.visible_total, icon: Users, tone: 'text-primary' },
                        { label: t.plans, icon: ClipboardCheck, tone: 'text-teal-600' },
                        { label: t.reviews, icon: Clock3, tone: 'text-amber-700' },
                    ].map(({ label, icon: Icon, tone }) => (
                        <div key={label} className="flex items-center gap-4">
                            <Icon className={`size-5 ${tone}`} aria-hidden="true" />
                            <span className="text-2xl font-semibold">{label === text.visible_total ? learners.total : '—'}</span>
                            <span className="text-muted-foreground text-xs">{label}</span>
                        </div>
                    ))}
                </div>
                <section className="bg-card overflow-hidden rounded-2xl border shadow-xs" aria-label={t.all}>
                    <div className="flex gap-5 overflow-x-auto border-b px-5 sm:px-6">
                        {[t.all, t.reviews_tab, t.draft_tab, t.archived].map((label, index) => (
                            <button
                                key={label}
                                type="button"
                                disabled={index !== 0}
                                title={index ? t.unavailable : undefined}
                                className={`shrink-0 border-b-2 py-4 text-xs ${index === 0 ? 'border-primary text-primary' : 'text-muted-foreground border-transparent disabled:opacity-60'}`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                    <div className="flex flex-wrap items-center gap-3 border-b p-5 sm:px-6">
                        <div className="bg-background text-muted-foreground flex h-10 min-w-48 flex-1 items-center gap-2 rounded-xl border px-3">
                            <Search className="size-4" aria-hidden="true" />
                            <input
                                aria-label={t.search_hint}
                                placeholder={t.search_hint}
                                disabled
                                className="placeholder:text-muted-foreground w-full min-w-0 bg-transparent text-xs outline-none"
                            />
                        </div>
                        {[t.all_years, t.all_sections].map((label) => (
                            <Button key={label} variant="outline" disabled title={t.unavailable} className="rounded-xl text-xs">
                                {label}
                                <ChevronDown className="size-4" />
                            </Button>
                        ))}
                        <Button variant="outline" disabled title={t.unavailable} className="rounded-xl text-xs">
                            <SlidersHorizontal className="size-4" />
                            {t.filters}
                        </Button>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[800px] text-left">
                            <caption className="sr-only">{t.all}</caption>
                            <thead className="bg-background text-muted-foreground border-b text-[11px]">
                                <tr>
                                    {[t.learner, t.lrn, t.year, t.section, t.level, t.plan, t.review, t.guardians].map((label) => (
                                        <th key={label} scope="col" className="px-5 py-4 font-medium">
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {learners.data.map((learner) => (
                                    <tr key={learner.id} className="border-b text-xs last:border-0">
                                        <td className="px-5 py-5">
                                            <span className="font-semibold">{learner.name}</span>
                                            <span className="text-muted-foreground mt-1 block">{learner.smile_id}</span>
                                        </td>
                                        <td className="text-muted-foreground px-5 py-5">{learner.lrn ?? '—'}</td>
                                        <td className="px-5 py-5">{learner.enrollments.map((enrollment) => enrollment.year).join(', ')}</td>
                                        <td className="px-5 py-5">{learner.enrollments.map((enrollment) => enrollment.section).join(', ')}</td>
                                        <td className="px-5 py-5">
                                            <span className="rounded-md bg-teal-50 px-2 py-1 text-teal-700">{text.non_graded}</span>
                                        </td>
                                        {[t.plan, t.review].map((label) => (
                                            <td key={label} className="text-muted-foreground px-5 py-5">
                                                —
                                            </td>
                                        ))}
                                        <td className="px-5 py-5">
                                            <Link
                                                href={`/learners/${learner.id}/guardians`}
                                                className="text-primary inline-flex min-h-11 items-center underline-offset-4 hover:underline"
                                            >
                                                {text.contacts}
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {learners.data.length === 0 && (
                        <WorkspaceEmpty icon={Users} title={text.roster_empty} detail={text.roster_detail}>
                            {sectionSettingsNav.visible && (
                                <Button asChild variant="outline" className="rounded-xl">
                                    <Link href="/settings/sections">
                                        {t.setup}
                                        <ChevronRight className="size-4" />
                                    </Link>
                                </Button>
                            )}
                        </WorkspaceEmpty>
                    )}
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-5 sm:px-6">
                        <span className="text-muted-foreground text-xs">
                            {text.showing} {learners.from ?? 0}–{learners.to ?? 0} {text.of} {learners.total}
                        </span>
                        <div className="flex gap-2">
                            {learners.prev_page_url ? (
                                <Button asChild variant="outline" className="min-h-11 rounded-xl text-xs">
                                    <Link href={learners.prev_page_url}>{t.previous}</Link>
                                </Button>
                            ) : (
                                <Button variant="outline" disabled className="rounded-xl text-xs">
                                    <ChevronLeft className="size-4" />
                                    {t.previous}
                                </Button>
                            )}
                            {learners.next_page_url ? (
                                <Button asChild variant="outline" className="min-h-11 rounded-xl text-xs">
                                    <Link href={learners.next_page_url}>{t.next}</Link>
                                </Button>
                            ) : (
                                <Button variant="outline" disabled className="rounded-xl text-xs">
                                    {t.next}
                                    <ChevronRight className="size-4" />
                                </Button>
                            )}
                        </div>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
