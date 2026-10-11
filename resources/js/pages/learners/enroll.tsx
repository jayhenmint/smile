import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { WorkspaceCard } from '@/components/workspace-ui';
import AppLayout from '@/layouts/app-layout';
import { type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';

type Props = {
    learner: { id: string; name: string; smile_id: string };
    sections: { id: number; name: string; year: string }[];
    today: string;
    text: Record<string, string>;
};

export default function Enroll({ learner, sections, today, text: t }: Props) {
    const { workspace, sectionSettingsNav } = usePage<SharedData>().props;
    const form = useForm({ section_id: '', enrolled_on: today });
    const url = `/learners/${learner.id}`;
    const selected = sections.find((section) => String(section.id) === form.data.section_id);
    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`${url}/enrollments`);
    }
    return (
        <AppLayout
            breadcrumbs={[
                { title: t.learners, href: '/learners' },
                { title: t.profile, href: url },
                { title: t.reenroll, href: `${url}/enrollments/create` },
            ]}
        >
            <Head title={t.reenroll} />
            <div className="mx-auto w-full max-w-3xl space-y-6 p-4 sm:p-6 lg:p-8">
                <div>
                    <h1 className="text-2xl font-bold">{t.reenroll}</h1>
                    <p className="mt-2 font-medium">
                        {learner.name} <span className="text-muted-foreground ml-2 text-sm">{learner.smile_id}</span>
                    </p>
                    <p className="text-muted-foreground mt-3 text-sm leading-6">{t.reenroll_hint}</p>
                </div>
                <WorkspaceCard>
                    <dl className="mb-6 grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt className="text-muted-foreground text-xs">{t.current_school_year}</dt>
                            <dd className="mt-1 text-sm font-medium">{workspace.schoolYear ?? t.no_current_year}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground text-xs">{t.enrollment_school_year}</dt>
                            <dd className="mt-1 text-sm font-medium">{selected?.year ?? t.select_section_first}</dd>
                        </div>
                    </dl>
                    {sections.length === 0 ? (
                        <div role="status" className="space-y-3 text-sm">
                            <p>{t.no_enrollment_sections}</p>
                            {sectionSettingsNav.visible && (
                                <Link href="/settings/sections" className="text-primary inline-flex min-h-11 items-center underline">
                                    {t.setup_sections}
                                </Link>
                            )}
                        </div>
                    ) : (
                        <form onSubmit={submit} className="space-y-5">
                            <InputError message={(form.errors as Record<string, string>).form} />
                            <div className="space-y-2">
                                <Label htmlFor="section_id">{t.section_id}</Label>
                                <select
                                    id="section_id"
                                    required
                                    value={form.data.section_id}
                                    onChange={(event) => form.setData('section_id', event.target.value)}
                                    aria-invalid={!!form.errors.section_id}
                                    aria-describedby="section-error"
                                    className="border-input bg-background min-h-11 w-full rounded-xl border px-3 text-sm"
                                >
                                    <option value="">{t.choose}</option>
                                    {sections.map((section) => (
                                        <option key={section.id} value={section.id}>
                                            {section.name} ({section.year}){section.year === workspace.schoolYear ? ` - ${t.current}` : ''}
                                        </option>
                                    ))}
                                </select>
                                <InputError id="section-error" message={form.errors.section_id} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="enrolled_on">{t.enrolled_on}</Label>
                                <Input
                                    id="enrolled_on"
                                    type="date"
                                    required
                                    value={form.data.enrolled_on}
                                    onChange={(event) => form.setData('enrolled_on', event.target.value)}
                                    aria-invalid={!!form.errors.enrolled_on}
                                    aria-describedby="enrolled-on-error"
                                    className="min-h-11 rounded-xl"
                                />
                                <InputError id="enrolled-on-error" message={form.errors.enrolled_on} />
                            </div>
                            <p className="text-muted-foreground text-sm">{t.level_hint}</p>
                            <p className="text-muted-foreground text-xs leading-6">{t.documents_hint}</p>
                            <Button type="submit" disabled={form.processing} className="min-h-11 rounded-xl">
                                {t.reenroll}
                            </Button>
                        </form>
                    )}
                </WorkspaceCard>
                <Button asChild variant="outline" className="min-h-11 rounded-xl">
                    <Link href={url}>{t.cancel}</Link>
                </Button>
            </div>
        </AppLayout>
    );
}
