import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { WorkspaceCard } from '@/components/workspace-ui';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

type Contact = {
    id: string;
    full_name: string;
    relationship: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    is_primary: boolean;
    is_emergency: boolean;
    can_edit: boolean;
};
type ContactForm = {
    full_name: string;
    relationship: string;
    phone: string;
    email: string;
    address: string;
    is_primary: boolean;
    is_emergency: boolean;
};
type Props = { learner: { id: string; name: string; smile_id: string }; contacts: Contact[]; text: Record<string, string>; saved: boolean };

export default function Guardians({ learner, contacts, text: t, saved }: Props) {
    const url = `/learners/${learner.id}/guardians`;
    const [editing, setEditing] = useState<string | null>(null);
    const form = useForm<ContactForm>({ full_name: '', relationship: '', phone: '', email: '', address: '', is_primary: false, is_emergency: false });
    function submit(event: FormEvent) {
        event.preventDefault();
        if (editing) {
            form.patch(`${url}/${editing}`, { preserveScroll: true, preserveState: 'errors' });
        } else {
            form.post(url, { preserveScroll: true, onSuccess: () => form.reset() });
        }
    }
    function edit(contact: Contact) {
        form.clearErrors();
        form.setData({
            full_name: contact.full_name,
            relationship: contact.relationship,
            phone: contact.phone ?? '',
            email: contact.email ?? '',
            address: contact.address ?? '',
            is_primary: contact.is_primary,
            is_emergency: contact.is_emergency,
        });
        setEditing(contact.id);
        requestAnimationFrame(() => document.getElementById('full_name')?.focus());
    }
    function cancel() {
        form.reset();
        form.clearErrors();
        setEditing(null);
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
                        {learner.name} <span className="text-muted-foreground ml-2 text-sm">{learner.smile_id}</span>
                    </p>
                    <p className="text-muted-foreground mt-2 text-sm">{t.subtitle}</p>
                </div>
                {saved && (
                    <p role="status" className="rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-800">
                        {t.saved}
                    </p>
                )}
                <div className="grid items-start gap-6 lg:grid-cols-2">
                    <div className="space-y-4">
                        {contacts.length === 0 && (
                            <WorkspaceCard>
                                <p className="text-muted-foreground text-sm">{t.empty}</p>
                            </WorkspaceCard>
                        )}
                        {contacts.map((contact) => (
                            <WorkspaceCard key={contact.id}>
                                <h2 className="font-semibold">{contact.full_name}</h2>
                                <p className="text-muted-foreground mt-1 text-sm">{contact.relationship}</p>
                                <dl className="mt-4 space-y-3 text-sm">
                                    {(['phone', 'email', 'address'] as const).map((field) => (
                                        <div key={field}>
                                            <dt className="text-muted-foreground text-xs">{t[field]}</dt>
                                            <dd className="mt-1 break-words whitespace-pre-wrap">{contact[field] ?? '—'}</dd>
                                        </div>
                                    ))}
                                </dl>
                                <div className="mt-4 flex flex-wrap gap-2 text-xs">
                                    {contact.is_primary && <span className="bg-primary/10 text-primary rounded-md px-2 py-1">{t.is_primary}</span>}
                                    {contact.is_emergency && (
                                        <span className="rounded-md bg-amber-50 px-2 py-1 text-amber-800">{t.is_emergency}</span>
                                    )}
                                </div>
                                {contact.can_edit ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="mt-4 min-h-11 rounded-xl"
                                        disabled={form.processing}
                                        onClick={() => edit(contact)}
                                    >
                                        {t.edit}
                                        <span className="sr-only">: {contact.full_name}</span>
                                    </Button>
                                ) : (
                                    <p className="text-muted-foreground mt-4 text-xs">{t.shared_readonly}</p>
                                )}
                            </WorkspaceCard>
                        ))}
                    </div>
                    <WorkspaceCard>
                        <h2 className="mb-4 font-semibold">{editing ? t.editing : t.add}</h2>
                        <form onSubmit={submit} className="space-y-5">
                            <InputError message={(form.errors as Record<string, string>).form} />
                            {(['full_name', 'relationship', 'phone', 'email', 'address'] as const).map((field) => (
                                <div key={field} className="space-y-2">
                                    <Label htmlFor={field}>{t[field]}</Label>
                                    <Input
                                        id={field}
                                        type={field === 'email' ? 'email' : field === 'phone' ? 'tel' : 'text'}
                                        required={field === 'full_name' || field === 'relationship'}
                                        maxLength={field === 'address' ? 2000 : field === 'phone' ? 50 : field === 'relationship' ? 100 : 255}
                                        value={form.data[field]}
                                        onChange={(event) => form.setData(field, event.target.value)}
                                        aria-invalid={!!form.errors[field]}
                                        aria-describedby={`${field}-error`}
                                        className="min-h-11 rounded-xl"
                                    />
                                    <InputError id={`${field}-error`} message={form.errors[field]} />
                                </div>
                            ))}
                            {(['is_primary', 'is_emergency'] as const).map((field) => (
                                <div key={field}>
                                    <label className="flex min-h-11 items-center gap-3 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={form.data[field]}
                                            onChange={(event) => form.setData(field, event.target.checked)}
                                            className="size-5 accent-indigo-600"
                                            aria-describedby={`${field}-error`}
                                        />
                                        {t[field]}
                                    </label>
                                    <InputError id={`${field}-error`} message={form.errors[field]} />
                                </div>
                            ))}
                            <p className="text-muted-foreground text-xs leading-6">{t.hint}</p>
                            <Button type="submit" disabled={form.processing} className="min-h-11 rounded-xl">
                                {t.save}
                            </Button>
                            {editing && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="ml-3 min-h-11 rounded-xl"
                                    disabled={form.processing}
                                    onClick={cancel}
                                >
                                    {t.cancel}
                                </Button>
                            )}
                        </form>
                    </WorkspaceCard>
                </div>
                <Button asChild variant="outline" className="min-h-11 rounded-xl">
                    <Link href="/learners">{t.back}</Link>
                </Button>
            </div>
        </AppLayout>
    );
}
