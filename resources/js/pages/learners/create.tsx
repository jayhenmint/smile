import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { WorkspaceCard } from '@/components/workspace-ui';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

type Props = { text: Record<string, string>; today: string; sections: { id: number; name: string; year: string }[] };
export default function CreateLearner({ text: t, today, sections }: Props) {
    const form = useForm<{
        first_name: string;
        last_name: string;
        middle_name: string;
        birthdate: string;
        sex: string;
        address: string;
        mother_tongue: string;
        lrn: string;
        lrn_status: string;
        section_id: string;
        enrolled_on: string;
        is_transferee: boolean;
        previous_school: string;
        health_notes: string;
        consent_confirmed: boolean;
    }>({
        first_name: '',
        last_name: '',
        middle_name: '',
        birthdate: '',
        sex: '',
        address: '',
        mother_tongue: '',
        lrn: '',
        lrn_status: 'pending',
        section_id: '',
        enrolled_on: today,
        is_transferee: false,
        previous_school: '',
        health_notes: '',
        consent_confirmed: false,
    });
    const field = (
        key: 'first_name' | 'last_name' | 'middle_name' | 'birthdate' | 'address' | 'mother_tongue' | 'lrn' | 'enrolled_on' | 'previous_school',
        required = false,
    ) => (
        <div className="space-y-2" key={key}>
            <Label htmlFor={key}>{t[key]}</Label>
            <Input
                id={key}
                value={form.data[key]}
                required={required}
                type={key === 'birthdate' || key === 'enrolled_on' ? 'date' : 'text'}
                inputMode={key === 'lrn' ? 'numeric' : undefined}
                maxLength={key === 'lrn' ? 12 : key === 'address' ? 2000 : 255}
                onChange={(e) => form.setData(key, e.target.value)}
                aria-invalid={!!form.errors[key]}
                aria-describedby={`${key}-error`}
                className="min-h-11 rounded-xl"
            />
            <InputError id={`${key}-error`} message={form.errors[key]} />
        </div>
    );
    const select = (key: 'sex' | 'lrn_status' | 'section_id', options: { value: string; label: string }[], required = false) => (
        <div className="space-y-2">
            <Label htmlFor={key}>{t[key]}</Label>
            <select
                id={key}
                value={form.data[key]}
                required={required}
                onChange={(e) => form.setData(key, e.target.value)}
                aria-invalid={!!form.errors[key]}
                aria-describedby={`${key}-error`}
                className="border-input bg-background min-h-11 w-full rounded-xl border px-3 text-sm"
            >
                <option value="">{t.choose}</option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            <InputError id={`${key}-error`} message={form.errors[key]} />
        </div>
    );
    function submit(e: FormEvent) {
        e.preventDefault();
        form.post('/learners', { onSuccess: () => form.reset() });
    }
    return (
        <AppLayout
            breadcrumbs={[
                { title: t.learners, href: '/learners' },
                { title: t.add, href: '/learners/create' },
            ]}
        >
            <Head title={t.add} />
            <form onSubmit={submit} className="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
                <h1 className="text-2xl font-bold">{t.add}</h1>
                {sections.length === 0 && (
                    <p role="status" className="rounded-xl border p-4 text-sm">
                        {t.no_sections}
                    </p>
                )}
                <InputError message={(form.errors as Record<string, string>).form} />
                <div className="grid items-start gap-6 lg:grid-cols-[1.5fr_1fr]">
                    <WorkspaceCard>
                        <h2 className="mb-5 font-semibold">{t.identity}</h2>
                        <p className="text-muted-foreground mb-5 text-sm">{t.id_hint}</p>
                        <div className="grid gap-5 sm:grid-cols-2">
                            {field('first_name', true)}
                            {field('last_name', true)}
                            {field('middle_name')}
                            {field('birthdate', true)}
                            {select(
                                'sex',
                                ['M', 'F'].map((value) => ({ value, label: t[value] })),
                            )}
                            {field('mother_tongue')}
                            {field('lrn')}
                            {select(
                                'lrn_status',
                                ['pending', 'issued', 'needs_correction'].map((value) => ({ value, label: t[value] })),
                                true,
                            )}
                        </div>
                        <div className="mt-5">{field('address')}</div>
                    </WorkspaceCard>
                    <div className="space-y-6">
                        <WorkspaceCard>
                            <h2 className="mb-5 font-semibold">{t.enrollment}</h2>
                            <div className="space-y-5">
                                {select(
                                    'section_id',
                                    sections.map((section) => ({ value: String(section.id), label: `${section.name} · ${section.year}` })),
                                    true,
                                )}
                                <p className="text-muted-foreground text-sm">
                                    <span className="text-foreground font-medium">{t.non_graded}</span>
                                    <br />
                                    {t.level_hint}
                                </p>
                                {field('enrolled_on', true)}
                                <label className="flex min-h-11 items-center gap-3 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={form.data.is_transferee}
                                        onChange={(e) => form.setData('is_transferee', e.target.checked)}
                                        className="size-5 accent-indigo-600"
                                    />
                                    {t.is_transferee}
                                </label>
                                {field('previous_school')}
                            </div>
                        </WorkspaceCard>
                        <WorkspaceCard>
                            <Label htmlFor="health_notes">{t.health_notes}</Label>
                            <p className="text-muted-foreground my-3 text-xs leading-5">{t.health_hint}</p>
                            <textarea
                                id="health_notes"
                                value={form.data.health_notes}
                                maxLength={5000}
                                rows={4}
                                onChange={(e) => form.setData('health_notes', e.target.value)}
                                aria-describedby="health_notes-error"
                                className="border-input bg-background w-full rounded-xl border p-3 text-sm"
                            />
                            <InputError id="health_notes-error" message={form.errors.health_notes} />
                        </WorkspaceCard>
                    </div>
                </div>
                <WorkspaceCard>
                    <label className="flex min-h-11 items-start gap-3 text-sm">
                        <input
                            type="checkbox"
                            required
                            checked={form.data.consent_confirmed}
                            onChange={(e) => form.setData('consent_confirmed', e.target.checked)}
                            className="mt-0.5 size-5 shrink-0 accent-indigo-600"
                            aria-describedby="consent-error"
                        />
                        {t.consent_confirmed}
                    </label>
                    <InputError id="consent-error" message={form.errors.consent_confirmed} />
                    <p className="text-muted-foreground mt-3 text-xs leading-6">{t.documents_hint}</p>
                </WorkspaceCard>
                <div className="flex justify-end gap-3">
                    <Button variant="outline" asChild className="min-h-11 rounded-xl">
                        <Link href="/learners">{t.cancel}</Link>
                    </Button>
                    <Button type="submit" disabled={form.processing || sections.length === 0} className="min-h-11 rounded-xl">
                        {t.save}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
