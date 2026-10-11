import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { WorkspaceCard } from '@/components/workspace-ui';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';

type Props = {
    learner: { id: string; name: string; smile_id: string };
    text: Record<string, string>;
    today: string;
    saved: boolean;
    categories: { id: number; name: string }[];
    records: {
        teacher: { id: number; assessed_on: string; observations: string }[];
        disabilities: { id: number; category: string; diagnosis_date: string | null; assessed_by: string | null; notes: string | null }[];
    };
};

export default function Assessments({ learner, text: t, today, saved, categories, records }: Props) {
    const url = `/learners/${learner.id}/assessments`;
    const teacher = useForm({ kind: 'teacher', assessed_on: today, observations: '' });
    const disability = useForm({ kind: 'disability', category_id: '', diagnosis_date: '', assessed_by: '', notes: '' });
    function saveTeacher(event: FormEvent) {
        event.preventDefault();
        teacher.post(url, { preserveScroll: true, onSuccess: () => teacher.reset() });
    }
    function saveDisability(event: FormEvent) {
        event.preventDefault();
        disability.post(url, { preserveScroll: true, onSuccess: () => disability.reset() });
    }
    return (
        <AppLayout
            breadcrumbs={[
                { title: t.learners, href: '/learners' },
                { title: t.title, href: url },
            ]}
        >
            <Head title={t.title} />
            <div className="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">
                <div>
                    <h1 className="text-2xl font-bold">{t.title}</h1>
                    <p className="mt-2 font-medium">
                        {learner.name}
                        <span className="text-muted-foreground ml-2 text-sm">{learner.smile_id}</span>
                    </p>
                </div>
                <p className="bg-primary/5 text-muted-foreground rounded-xl border p-4 text-sm leading-6">{t.hint}</p>
                {saved && (
                    <p role="status" className="rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-800">
                        {t.saved}
                    </p>
                )}
                <div className="grid items-start gap-6 lg:grid-cols-2">
                    <div className="space-y-4">
                        <WorkspaceCard>
                            <h2 className="mb-5 font-semibold">{t.teacher_title}</h2>
                            <form onSubmit={saveTeacher} className="space-y-5">
                                <InputError message={(teacher.errors as Record<string, string>).form} />
                                <div className="space-y-2">
                                    <Label htmlFor="assessed_on">{t.assessed_on}</Label>
                                    <Input
                                        id="assessed_on"
                                        type="date"
                                        required
                                        value={teacher.data.assessed_on}
                                        onChange={(e) => teacher.setData('assessed_on', e.target.value)}
                                        aria-describedby="assessed-on-error"
                                        className="min-h-11 rounded-xl"
                                    />
                                    <InputError id="assessed-on-error" message={teacher.errors.assessed_on} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="observations">{t.observations}</Label>
                                    <textarea
                                        id="observations"
                                        required
                                        rows={5}
                                        maxLength={10000}
                                        value={teacher.data.observations}
                                        onChange={(e) => teacher.setData('observations', e.target.value)}
                                        aria-describedby="observations-error"
                                        className="border-input bg-background w-full rounded-xl border p-3 text-sm"
                                    />
                                    <InputError id="observations-error" message={teacher.errors.observations} />
                                </div>
                                <Button type="submit" disabled={teacher.processing} className="min-h-11 rounded-xl">
                                    {t.save_teacher}
                                </Button>
                            </form>
                        </WorkspaceCard>
                        {records.teacher.length === 0 && <p className="text-muted-foreground text-sm">{t.empty_teacher}</p>}
                        {records.teacher.map((record) => (
                            <WorkspaceCard key={record.id}>
                                <p className="text-muted-foreground text-xs">{record.assessed_on}</p>
                                <p className="mt-3 text-sm break-words whitespace-pre-wrap">{record.observations}</p>
                            </WorkspaceCard>
                        ))}
                    </div>
                    <div className="space-y-4">
                        <WorkspaceCard>
                            <h2 className="mb-5 font-semibold">{t.disability_title}</h2>
                            <form onSubmit={saveDisability} className="space-y-5">
                                <InputError message={(disability.errors as Record<string, string>).form} />
                                <div className="space-y-2">
                                    <Label htmlFor="category_id">{t.category_id}</Label>
                                    <select
                                        id="category_id"
                                        required
                                        value={disability.data.category_id}
                                        onChange={(e) => disability.setData('category_id', e.target.value)}
                                        aria-describedby="category-error"
                                        className="border-input bg-background min-h-11 w-full rounded-xl border px-3 text-sm"
                                    >
                                        <option value="">{t.choose}</option>
                                        {categories.map((category) => (
                                            <option key={category.id} value={category.id}>
                                                {category.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError id="category-error" message={disability.errors.category_id} />
                                </div>
                                {(['diagnosis_date', 'assessed_by'] as const).map((field) => (
                                    <div key={field} className="space-y-2">
                                        <Label htmlFor={field}>{t[field]}</Label>
                                        <Input
                                            id={field}
                                            type={field === 'diagnosis_date' ? 'date' : 'text'}
                                            maxLength={255}
                                            value={disability.data[field]}
                                            onChange={(e) => disability.setData(field, e.target.value)}
                                            aria-describedby={`${field}-error`}
                                            className="min-h-11 rounded-xl"
                                        />
                                        <InputError id={`${field}-error`} message={disability.errors[field]} />
                                    </div>
                                ))}
                                <div className="space-y-2">
                                    <Label htmlFor="notes">{t.notes}</Label>
                                    <textarea
                                        id="notes"
                                        rows={5}
                                        maxLength={10000}
                                        value={disability.data.notes}
                                        onChange={(e) => disability.setData('notes', e.target.value)}
                                        aria-describedby="notes-error"
                                        className="border-input bg-background w-full rounded-xl border p-3 text-sm"
                                    />
                                    <InputError id="notes-error" message={disability.errors.notes} />
                                </div>
                                <Button type="submit" disabled={disability.processing} className="min-h-11 rounded-xl">
                                    {t.save_disability}
                                </Button>
                            </form>
                        </WorkspaceCard>
                        {records.disabilities.length === 0 && <p className="text-muted-foreground text-sm">{t.empty_disability}</p>}
                        {records.disabilities.map((record) => (
                            <WorkspaceCard key={record.id}>
                                <h3 className="font-semibold">{record.category}</h3>
                                <dl className="mt-4 space-y-3 text-sm">
                                    {(['diagnosis_date', 'assessed_by', 'notes'] as const).map((field) => (
                                        <div key={field}>
                                            <dt className="text-muted-foreground text-xs">{t[field]}</dt>
                                            <dd className="mt-1 break-words whitespace-pre-wrap">{record[field] ?? '—'}</dd>
                                        </div>
                                    ))}
                                </dl>
                            </WorkspaceCard>
                        ))}
                    </div>
                </div>
                <Button asChild variant="outline" className="min-h-11 rounded-xl">
                    <Link href="/learners">{t.back}</Link>
                </Button>
            </div>
        </AppLayout>
    );
}
