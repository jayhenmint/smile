import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

type Account = { id: string; name: string; email: string; is_active: boolean; roles: string[]; can_update: boolean };
type Accounts = { data: Account[]; prev_page_url: string | null; next_page_url: string | null };
type AccountForm = { name: string; email: string; password: string; password_confirmation: string; school_admin: boolean };

function AccountAccess({ account, labels }: { account: Account; labels: Record<string, string> }) {
    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        school_admin: account.roles.includes('school_admin'),
        is_active: account.is_active,
    });
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        patch(route('accounts.update', { account: account.id }), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="mt-3 space-y-2" aria-label={`${labels.manage_title}: ${account.name}`}>
            {(['school_admin', 'is_active'] as const).map((field) => (
                <div key={field}>
                    <Label htmlFor={`${account.id}-${field}`} className="flex min-h-11 items-center gap-3">
                        <input
                            id={`${account.id}-${field}`}
                            type="checkbox"
                            checked={data[field]}
                            onChange={(event) => setData(field, event.target.checked)}
                            disabled={processing}
                            aria-invalid={!!errors[field]}
                            aria-describedby={`${account.id}-${field}-error`}
                            className="size-5"
                        />
                        {field === 'school_admin' ? labels.school_admin : labels.active_option}
                    </Label>
                    <div id={`${account.id}-${field}-error`}>
                        <InputError message={errors[field]} />
                    </div>
                </div>
            ))}
            <div className="flex flex-wrap items-center gap-3">
                <Button type="submit" variant="outline" disabled={processing} className="min-h-11">
                    {processing ? labels.saving_access : labels.save_access}
                </Button>
                <p role="status" aria-live="polite" className="text-muted-foreground text-sm">
                    {recentlySuccessful ? labels.access_saved : ''}
                </p>
            </div>
        </form>
    );
}

export default function Accounts({ accounts, labels }: { accounts: Accounts; labels: Record<string, string> }) {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, reset, errors, processing, recentlySuccessful } = useForm<AccountForm>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        school_admin: false,
    });
    const generatePassword = () => {
        const bytes = crypto.getRandomValues(new Uint8Array(16));
        const password = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
        setData((current) => ({ ...current, password, password_confirmation: password }));
        setShowPassword(true);
    };
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('accounts.store'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onFinish: () => {
                reset('password', 'password_confirmation');
                setShowPassword(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={[{ title: labels.title, href: '/settings/accounts' }]}>
            <Head title={`${labels.title} | SMILE`} />
            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title={labels.create_title} description={labels.description} />
                    <form onSubmit={submit} className="space-y-6">
                        {(['name', 'email', 'password', 'password_confirmation'] as const).map((field) => (
                            <div key={field} className="grid gap-2">
                                <Label htmlFor={`account-${field}`}>{labels[field]}</Label>
                                <Input
                                    id={`account-${field}`}
                                    name={field}
                                    value={data[field]}
                                    required
                                    type={field.startsWith('password') ? (showPassword ? 'text' : 'password') : field === 'email' ? 'email' : 'text'}
                                    autoComplete={field.startsWith('password') ? 'new-password' : field === 'email' ? 'off' : 'name'}
                                    minLength={field.startsWith('password') ? 8 : undefined}
                                    maxLength={255}
                                    onChange={(event) => setData(field, field === 'email' ? event.target.value.toLowerCase() : event.target.value)}
                                    aria-invalid={!!errors[field]}
                                    aria-describedby={`account-${field}-error`}
                                    className="min-h-11"
                                />
                                <div id={`account-${field}-error`}>
                                    <InputError message={errors[field]} />
                                </div>
                            </div>
                        ))}
                        <div className="flex flex-wrap gap-3">
                            <Button type="button" variant="outline" onClick={generatePassword} disabled={processing} className="min-h-11">
                                {labels.generate_password}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setShowPassword((visible) => !visible)}
                                disabled={processing}
                                aria-pressed={showPassword}
                                aria-controls="account-password account-password_confirmation"
                                className="min-h-11"
                            >
                                {showPassword ? labels.hide_password : labels.show_password}
                            </Button>
                        </div>
                        <p className="text-muted-foreground text-sm">{labels.generate_help}</p>
                        <p className="text-muted-foreground text-sm">{labels.password_help}</p>
                        <p className="text-muted-foreground text-sm">{labels.teacher_help}</p>
                        <div className="space-y-2">
                            <Label htmlFor="account-school-admin" className="flex min-h-11 items-center gap-3">
                                <input
                                    id="account-school-admin"
                                    type="checkbox"
                                    checked={data.school_admin}
                                    onChange={(event) => setData('school_admin', event.target.checked)}
                                    aria-describedby="account-admin-help"
                                    className="size-5"
                                />
                                {labels.school_admin}
                            </Label>
                            <p id="account-admin-help" className="text-muted-foreground text-sm">
                                {labels.admin_help}
                            </p>
                            <InputError message={errors.school_admin} />
                        </div>
                        <div className="flex flex-wrap items-center gap-4">
                            <Button type="submit" disabled={processing} className="min-h-11">
                                {processing ? labels.creating : labels.create}
                            </Button>
                            <p role="status" aria-live="polite" className="text-muted-foreground text-sm">
                                {recentlySuccessful ? labels.created : ''}
                            </p>
                        </div>
                    </form>
                </div>
                <div className="space-y-4">
                    <HeadingSmall title={labels.list_title} description={labels.manage_help} />
                    <ul className="space-y-3">
                        {accounts.data.map((account) => (
                            <li key={account.id} className="space-y-1 rounded-md border p-4">
                                <p className="font-medium break-words">{account.name}</p>
                                <p className="text-muted-foreground text-sm break-all">{account.email}</p>
                                <p className="text-sm">
                                    {account.roles
                                        .map((role) => (role === 'school_admin' ? labels.school_admin_role : (labels[role] ?? role)))
                                        .join(', ')}
                                </p>
                                <p className="text-muted-foreground text-sm">{account.is_active ? labels.active : labels.inactive}</p>
                                {account.can_update ? (
                                    <AccountAccess
                                        key={`${account.id}-${account.is_active}-${account.roles.join(',')}`}
                                        account={account}
                                        labels={labels}
                                    />
                                ) : (
                                    <p className="text-muted-foreground text-sm">{labels.protected_account}</p>
                                )}
                            </li>
                        ))}
                    </ul>
                    {accounts.data.length === 0 && <p className="text-muted-foreground text-sm">{labels.empty}</p>}
                    <div className="flex gap-3">
                        {accounts.prev_page_url && (
                            <Button variant="outline" asChild className="min-h-11">
                                <Link href={accounts.prev_page_url}>{labels.previous}</Link>
                            </Button>
                        )}
                        {accounts.next_page_url && (
                            <Button variant="outline" asChild className="min-h-11">
                                <Link href={accounts.next_page_url}>{labels.next}</Link>
                            </Button>
                        )}
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
