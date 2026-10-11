import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { WorkspaceCard } from '@/components/workspace-ui';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

type Identity = {
    first_name: string;
    last_name: string;
    middle_name: string;
    birthdate: string;
    sex: string;
    address: string;
    mother_tongue: string;
    health_notes: string;
};
type Props = {
    learner: {
        id: string;
        smile_id: string;
        lrn: string | null;
        lrn_status: string;
        consent_confirmed_at: string;
        enrollments: { year: string; section: string; enrolled_on: string }[];
    } & { [K in keyof Identity]: Identity[K] | null };
    text: Record<string, string>;
    editing: boolean;
    updated: boolean;
    lrnRecorded: boolean;
    canRecordLrn: boolean;
    canReenroll: boolean;
    enrollmentSaved: boolean;
};

export default function Profile({ learner, text: t, editing, updated, lrnRecorded, canRecordLrn, canReenroll, enrollmentSaved }: Props) {
    const url = `/learners/${learner.id}`;
    const lrnForm = useForm({ lrn: '' });
    const form = useForm<Identity>({
        first_name: learner.first_name ?? '',
        last_name: learner.last_name ?? '',
        middle_name: learner.middle_name ?? '',
        birthdate: learner.birthdate ?? '',
        sex: learner.sex ?? '',
        address: learner.address ?? '',
        mother_tongue: learner.mother_tongue ?? '',
        health_notes: learner.health_notes ?? '',
    });
    function submit(event: FormEvent) {
        event.preventDefault();
        if (editing) form.patch(url, { preserveState: false });
    }
    return (
        <AppLayout
            breadcrumbs={[
                { title: t.learners, href: '/learners' },
                { title: editing ? t.edit : t.profile, href: editing ? `${url}/edit` : url },
            ]}
        >
            <Head title={editing ? t.edit : t.profile} />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">{editing ? t.edit : t.profile}</h1>
                        <p className="text-muted-foreground mt-2 text-sm">
                            {learner.first_name} {learner.last_name} · {learner.smile_id}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        {!editing && canReenroll && (
                            <Button asChild variant="outline" className="min-h-11 rounded-xl">
                                <Link href={`${url}/enrollments/create`}>{t.reenroll}</Link>
                            </Button>
                        )}
                        {!editing && (
                            <Button asChild className="min-h-11 rounded-xl">
                                <Link href={`${url}/edit`}>{t.edit}</Link>
                            </Button>
                        )}
                        <Button asChild variant="outline" className="min-h-11 rounded-xl">
                            <Link href={`${url}/guardians`}>{t.contacts}</Link>
                        </Button>
                        <Button asChild variant="outline" className="min-h-11 rounded-xl">
                            <Link href={`${url}/assessments`}>{t.assessments}</Link>
                        </Button>
                    </div>
                </div>
                {enrollmentSaved && (
                    <p role="status" className="rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-800">
                        {t.enrollment_saved}
                    </p>
                )}
                {updated && (
                    <p role="status" className="rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-800">
                        {t.updated}
                    </p>
                )}
                {lrnRecorded && (
                    <p role="status" className="rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-800">
                        {t.lrn_recorded}
                    </p>
                )}
                {!editing && canRecordLrn && (
                    <WorkspaceCard>
                        <h2 className="mb-3 font-semibold">{t.record_lrn}</h2>
                        <p className="text-muted-foreground mb-4 text-sm leading-6">{t.record_lrn_hint}</p>
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                lrnForm.post(`${url}/lrn`, { preserveScroll: true, preserveState: 'errors' });
                            }}
                            className="space-y-4"
                        >
                            <InputError message={(lrnForm.errors as Record<string, string>).form} />
                            <div className="space-y-2">
                                <Label htmlFor="issued-lrn">{t.lrn}</Label>
                                <Input
                                    id="issued-lrn"
                                    type="text"
                                    inputMode="numeric"
                                    pattern="[0-9]{12}"
                                    minLength={12}
                                    maxLength={12}
                                    required
                                    value={lrnForm.data.lrn}
                                    onChange={(event) => lrnForm.setData('lrn', event.target.value)}
                                    aria-invalid={!!lrnForm.errors.lrn}
                                    aria-describedby="issued-lrn-error"
                                    className="min-h-11 rounded-xl"
                                />
                                <InputError id="issued-lrn-error" message={lrnForm.errors.lrn} />
                            </div>
                            <Button type="submit" disabled={lrnForm.processing} className="min-h-11 rounded-xl">
                                {t.record_lrn}
                            </Button>
                        </form>
                    </WorkspaceCard>
                )}
                <form onSubmit={submit} className="space-y-6">
                    <WorkspaceCard>
                        <h2 className="mb-5 font-semibold">{t.identity}</h2>
                        <InputError message={(form.errors as Record<string, string>).form} />
                        <div className="mb-5 grid gap-5 sm:grid-cols-3">
                            {[
                                { key: 'smile_id', value: learner.smile_id },
                                { key: 'lrn', value: learner.lrn ?? '—' },
                                { key: 'lrn_status', value: t[learner.lrn_status] },
                            ].map(({ key, value }) => (
                                <div key={key} className="space-y-2">
                                    <Label htmlFor={key}>{t[key]}</Label>
                                    <Input id={key} value={value} readOnly className="min-h-11 rounded-xl" />
                                </div>
                            ))}
                        </div>
                        <p className="text-muted-foreground mb-5 text-xs leading-6">{t.identity_readonly}</p>
                        <div className="grid gap-5 sm:grid-cols-2">
                            {(['first_name', 'last_name', 'middle_name', 'birthdate', 'mother_tongue', 'address'] as const).map((field) => (
                                <div key={field} className="space-y-2">
                                    <Label htmlFor={field}>{t[field]}</Label>
                                    <Input
                                        id={field}
                                        type={field === 'birthdate' ? 'date' : 'text'}
                                        required={['first_name', 'last_name', 'birthdate'].includes(field)}
                                        readOnly={!editing}
                                        maxLength={field === 'address' ? 2000 : 255}
                                        value={form.data[field]}
                                        onChange={(e) => form.setData(field, e.target.value)}
                                        aria-invalid={!!form.errors[field]}
                                        aria-describedby={`${field}-error`}
                                        className="min-h-11 rounded-xl"
                                    />
                                    <InputError id={`${field}-error`} message={form.errors[field]} />
                                </div>
                            ))}
                            <div className="space-y-2">
                                <Label htmlFor="sex">{t.sex}</Label>
                                <select
                                    id="sex"
                                    disabled={!editing}
                                    value={form.data.sex}
                                    onChange={(e) => form.setData('sex', e.target.value)}
                                    aria-describedby="sex-error"
                                    className="border-input bg-background min-h-11 w-full rounded-xl border px-3 text-sm"
                                >
                                    <option value="">{t.choose}</option>
                                    <option value="M">{t.M}</option>
                                    <option value="F">{t.F}</option>
                                </select>
                                <InputError id="sex-error" message={form.errors.sex} />
                            </div>
                        </div>
                        <div className="mt-5 space-y-2">
                            <Label htmlFor="health_notes">{t.health_notes}</Label>
                            <textarea
                                id="health_notes"
                                readOnly={!editing}
                                rows={4}
                                maxLength={5000}
                                value={form.data.health_notes}
                                onChange={(e) => form.setData('health_notes', e.target.value)}
                                aria-describedby="health-error"
                                className="border-input bg-background w-full rounded-xl border p-3 text-sm"
                            />
                            <InputError id="health-error" message={form.errors.health_notes} />
                        </div>
                    </WorkspaceCard>
                    <WorkspaceCard>
                        <h2 className="mb-4 font-semibold">{t.enrollments}</h2>
                        <div className="space-y-3">
                            {learner.enrollments.map((enrollment, index) => (
                                <p key={index} className="text-sm">
                                    {enrollment.year} · {enrollment.section} · {t.non_graded} · {enrollment.enrolled_on}
                                </p>
                            ))}
                        </div>
                        <p className="text-muted-foreground mt-5 text-xs">
                            {t.consent_recorded} {learner.consent_confirmed_at.slice(0, 10)}
                        </p>
                    </WorkspaceCard>
                    <div className="flex justify-end gap-3">
                        <Button asChild variant="outline" className="min-h-11 rounded-xl">
                            <Link href={editing ? url : '/learners'}>{editing ? t.cancel : t.back}</Link>
                        </Button>
                        {editing && (
                            <Button type="submit" disabled={form.processing} className="min-h-11 rounded-xl">
                                {t.save}
                            </Button>
                        )}
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
