import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Year = { id: number; label: string; start_date: string; end_date: string; is_current: boolean };
type Years = { data: Year[]; prev_page_url: string | null; next_page_url: string | null };

function YearForm({ year, labels }: { year?: Year; labels: Record<string, string> }) {
    const { data, setData, post, patch, reset, errors, processing, recentlySuccessful } = useForm({
        label: year?.label ?? '',
        start_date: year?.start_date ?? '',
        end_date: year?.end_date ?? '',
        is_current: year?.is_current ?? false,
    });
    const prefix = `year-${year?.id ?? 'new'}`;
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        if (year) {
            patch(route('school-years.update', { schoolYear: year.id }), { preserveScroll: true });
        } else {
            post(route('school-years.store'), { preserveScroll: true, onSuccess: () => reset() });
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4" aria-label={year?.label ?? labels.create_title}>
            {(['label', 'start_date', 'end_date'] as const).map((field) => (
                <div key={field} className="grid gap-2">
                    <Label htmlFor={`${prefix}-${field}`}>{labels[field]}</Label>
                    <Input
                        id={`${prefix}-${field}`}
                        name={field}
                        type={field === 'label' ? 'text' : 'date'}
                        value={data[field]}
                        onChange={(event) => setData(field, event.target.value)}
                        required
                        maxLength={field === 'label' ? 100 : undefined}
                        min={field === 'end_date' ? data.start_date : undefined}
                        aria-invalid={!!errors[field]}
                        aria-describedby={`${prefix}-${field}-error`}
                        className="min-h-11"
                    />
                    <div id={`${prefix}-${field}-error`}>
                        <InputError message={errors[field]} />
                    </div>
                </div>
            ))}
            <Label htmlFor={`${prefix}-current`} className="flex min-h-11 items-center gap-3">
                <input
                    id={`${prefix}-current`}
                    type="checkbox"
                    checked={data.is_current}
                    onChange={(event) => setData('is_current', event.target.checked)}
                    disabled={processing}
                    aria-describedby={`${prefix}-current-help ${prefix}-current-error`}
                    aria-invalid={!!errors.is_current}
                    className="size-5"
                />
                {labels.is_current}
            </Label>
            <p id={`${prefix}-current-help`} className="text-muted-foreground text-sm">
                {labels.current_help}
            </p>
            <div id={`${prefix}-current-error`}>
                <InputError message={errors.is_current} />
            </div>
            <div className="flex flex-wrap items-center gap-3">
                <Button type="submit" disabled={processing} className="min-h-11">
                    {year ? (processing ? labels.saving : labels.save) : processing ? labels.creating : labels.create}
                </Button>
                <p role="status" aria-live="polite" className="text-muted-foreground text-sm">
                    {recentlySuccessful ? (year ? labels.saved : labels.created) : ''}
                </p>
            </div>
        </form>
    );
}

export default function SchoolYears({ schoolYears, canManage, labels }: { schoolYears: Years; canManage: boolean; labels: Record<string, string> }) {
    return (
        <AppLayout breadcrumbs={[{ title: labels.title, href: '/settings/school-years' }]}>
            <Head title={`${labels.title} | SMILE`} />
            <SettingsLayout>
                {canManage && (
                    <div className="space-y-6">
                        <HeadingSmall title={labels.create_title} description={labels.description} />
                        <p className="text-muted-foreground text-sm">{labels.label_help}</p>
                        <YearForm labels={labels} />
                    </div>
                )}
                <div className="space-y-4">
                    <HeadingSmall title={labels.list_title} />
                    <ul className="space-y-4">
                        {schoolYears.data.map((year) => (
                            <li key={year.id} className="space-y-4 rounded-md border p-4">
                                <p className="font-medium break-words">{year.label}</p>
                                <p className="text-sm">{year.is_current ? labels.current : labels.not_current}</p>
                                {canManage ? (
                                    <YearForm
                                        key={`${year.id}-${year.label}-${year.start_date}-${year.end_date}-${year.is_current}`}
                                        year={year}
                                        labels={labels}
                                    />
                                ) : (
                                    <p className="text-muted-foreground text-sm">
                                        <time dateTime={year.start_date}>{year.start_date}</time> –{' '}
                                        <time dateTime={year.end_date}>{year.end_date}</time>
                                    </p>
                                )}
                            </li>
                        ))}
                    </ul>
                    {schoolYears.data.length === 0 && <p className="text-muted-foreground text-sm">{labels.empty}</p>}
                    <div className="flex gap-3">
                        {schoolYears.prev_page_url && (
                            <Button variant="outline" asChild className="min-h-11">
                                <Link href={schoolYears.prev_page_url}>{labels.previous}</Link>
                            </Button>
                        )}
                        {schoolYears.next_page_url && (
                            <Button variant="outline" asChild className="min-h-11">
                                <Link href={schoolYears.next_page_url}>{labels.next}</Link>
                            </Button>
                        )}
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
