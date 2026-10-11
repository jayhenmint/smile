import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { WorkspaceCard } from '@/components/workspace-ui';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';

type Item = {
    id: number;
    name: string;
    required_for: string;
    from_party: string;
    status: string;
    submitted_on: string | null;
    notes: string | null;
    record_id: number | null;
    has_file: boolean;
    download_url: string | null;
};
type Props = {
    learner: { id: string; name: string; smile_id: string };
    items: Item[];
    text: Record<string, string>;
    saved: boolean;
    uploaded: boolean;
    maxUploadKb: number;
};

export default function Documents({ learner, items, text: t, saved, uploaded, maxUploadKb }: Props) {
    const url = `/learners/${learner.id}`;
    const [editing, setEditing] = useState<number | null>(null);
    const form = useForm({ status: 'missing', submitted_on: '', notes: '' });
    return (
        <AppLayout
            breadcrumbs={[
                { title: t.learners, href: '/learners' },
                { title: t.profile, href: url },
                { title: t.title, href: `${url}/documents` },
            ]}
        >
            <Head title={t.title} />
            <div className="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6 lg:p-8">
                <div>
                    <h1 className="text-2xl font-bold">{t.title}</h1>
                    <p className="mt-2 text-sm">
                        {learner.name} <span className="text-muted-foreground ml-2">{learner.smile_id}</span>
                    </p>
                </div>
                <WorkspaceCard>
                    <p className="text-sm leading-6">{t.hint}</p>
                    <p className="text-muted-foreground mt-3 text-sm leading-6">{t.scope_hint}</p>
                    <p className="text-muted-foreground mt-3 text-sm leading-6">{t.paper_hint}</p>
                </WorkspaceCard>
                {saved && (
                    <p role="status" className="rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-800">
                        {t.saved}
                    </p>
                )}
                {uploaded && (
                    <p role="status" className="rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-800">
                        {t.uploaded}
                    </p>
                )}
                <div className="space-y-4">
                    {items.map((item) => (
                        <WorkspaceCard key={item.id}>
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h2 className="font-semibold">{item.name}</h2>
                                    <p className="text-muted-foreground mt-2 text-xs">
                                        {t[item.required_for]} / {t[item.from_party]}
                                    </p>
                                </div>
                                <span className="bg-muted rounded-lg px-3 py-2 text-sm font-medium">{t[item.status]}</span>
                            </div>
                            {editing === item.id ? (
                                <form
                                    className="mt-5 space-y-4"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        form.put(`${url}/documents/${item.id}`, {
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                setEditing(null);
                                                form.reset();
                                            },
                                        });
                                    }}
                                >
                                    <InputError message={(form.errors as Record<string, string>).form} />
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor={`status-${item.id}`}>{t.status}</Label>
                                            <select
                                                id={`status-${item.id}`}
                                                value={form.data.status}
                                                onChange={(event) => {
                                                    form.setData('status', event.target.value);
                                                    if (event.target.value === 'missing') form.setData('submitted_on', '');
                                                }}
                                                aria-invalid={!!form.errors.status}
                                                aria-describedby="status-error"
                                                className="border-input bg-background min-h-11 w-full rounded-xl border px-3 text-sm"
                                            >
                                                {['missing', 'submitted', 'verified', 'expired'].map((status) => (
                                                    <option key={status} value={status}>
                                                        {t[status]}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError id="status-error" message={form.errors.status} />
                                        </div>
                                        {form.data.status !== 'missing' && (
                                            <div className="space-y-2">
                                                <Label htmlFor={`date-${item.id}`}>{t.submitted_on}</Label>
                                                <Input
                                                    id={`date-${item.id}`}
                                                    type="date"
                                                    required
                                                    value={form.data.submitted_on}
                                                    onChange={(event) => form.setData('submitted_on', event.target.value)}
                                                    aria-invalid={!!form.errors.submitted_on}
                                                    aria-describedby="date-error"
                                                    className="min-h-11 rounded-xl"
                                                />
                                                <InputError id="date-error" message={form.errors.submitted_on} />
                                            </div>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor={`notes-${item.id}`}>{t.notes}</Label>
                                        <textarea
                                            id={`notes-${item.id}`}
                                            value={form.data.notes}
                                            onChange={(event) => form.setData('notes', event.target.value)}
                                            maxLength={10000}
                                            aria-invalid={!!form.errors.notes}
                                            aria-describedby="notes-error"
                                            className="border-input bg-background min-h-28 w-full rounded-xl border p-3 text-sm"
                                        />
                                        <InputError id="notes-error" message={form.errors.notes} />
                                    </div>
                                    <div className="flex gap-3">
                                        <Button disabled={form.processing} className="min-h-11 rounded-xl">
                                            {t.save}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={form.processing}
                                            onClick={() => {
                                                setEditing(null);
                                                form.reset();
                                                form.clearErrors();
                                            }}
                                            className="min-h-11 rounded-xl"
                                        >
                                            {t.cancel}
                                        </Button>
                                    </div>
                                </form>
                            ) : (
                                <div className="mt-4 space-y-3">
                                    {item.submitted_on && (
                                        <p className="text-muted-foreground text-sm">
                                            {t.submitted_on}: {item.submitted_on}
                                        </p>
                                    )}
                                    {item.notes && <p className="text-sm whitespace-pre-wrap">{item.notes}</p>}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={form.processing}
                                        onClick={() => {
                                            setEditing(item.id);
                                            form.clearErrors();
                                            form.setData({ status: item.status, submitted_on: item.submitted_on ?? '', notes: item.notes ?? '' });
                                        }}
                                        className="min-h-11 rounded-xl"
                                    >
                                        {t.edit}
                                    </Button>
                                </div>
                            )}
                            <Attachment item={item} url={url} text={t} maxUploadKb={maxUploadKb} />
                        </WorkspaceCard>
                    ))}
                </div>
                <Button asChild variant="outline" className="min-h-11 rounded-xl">
                    <Link href={url}>{t.profile}</Link>
                </Button>
            </div>
        </AppLayout>
    );
}

function Attachment({ item, url, text: t, maxUploadKb }: { item: Item; url: string; text: Record<string, string>; maxUploadKb: number }) {
    const form = useForm<{ file: File | null }>({ file: null });
    const input = useRef<HTMLInputElement>(null);
    if (item.has_file && item.download_url)
        return (
            <div className="mt-5 border-t pt-4">
                <Button asChild variant="outline" className="min-h-11 rounded-xl">
                    <a href={item.download_url}>{t.download}</a>
                </Button>
                <p className="text-muted-foreground mt-2 text-xs leading-6">{t.download_hint}</p>
            </div>
        );
    if (!item.record_id || item.status === 'missing') return <p className="text-muted-foreground mt-4 text-xs leading-6">{t.record_submission}</p>;
    return (
        <form
            className="mt-5 space-y-3 border-t pt-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(`${url}/documents/files/${item.record_id}`, {
                    forceFormData: true,
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        if (input.current) input.current.value = '';
                    },
                });
            }}
        >
            <Label htmlFor={`file-${item.id}`}>{t.file}</Label>
            <Input
                ref={input}
                id={`file-${item.id}`}
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                required
                disabled={form.processing}
                onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)}
                aria-invalid={!!form.errors.file}
                aria-describedby={`file-hint-${item.id} file-error-${item.id}`}
                className="min-h-11 rounded-xl"
            />
            <p id={`file-hint-${item.id}`} className="text-muted-foreground text-xs">
                {t.file_hint} {maxUploadKb / 1024}
            </p>
            <InputError id={`file-error-${item.id}`} message={form.errors.file} />
            {form.progress && <progress aria-label={t.upload} value={form.progress.percentage} max={100} className="w-full" />}
            <Button type="submit" disabled={form.processing || !form.data.file} className="min-h-11 rounded-xl">
                {t.upload}
            </Button>
        </form>
    );
}
