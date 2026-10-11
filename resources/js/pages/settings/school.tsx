import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type SchoolDetails = {
    name: string;
    deped_school_id: string | null;
    division: string | null;
    region: string | null;
    address: string | null;
};

type Labels = Record<
    'title' | 'description' | 'name' | 'deped_school_id' | 'school_id_help' | 'division' | 'region' | 'address' | 'save' | 'saving' | 'saved',
    string
>;

export default function School({ school, labels }: { school: SchoolDetails; labels: Labels }) {
    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: school.name,
        deped_school_id: school.deped_school_id ?? '',
        division: school.division ?? '',
        region: school.region ?? '',
        address: school.address ?? '',
    });
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        patch(route('school.update'), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={[{ title: labels.title, href: '/settings/school' }]}>
            <Head title={`${labels.title} | SMILE`} />
            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title={labels.title} description={labels.description} />
                    <form onSubmit={submit} className="space-y-6">
                        {(['name', 'deped_school_id', 'division', 'region', 'address'] as const).map((field) => (
                            <div key={field} className="grid gap-2">
                                <Label htmlFor={`school-${field}`}>{labels[field]}</Label>
                                {field === 'address' ? (
                                    <textarea
                                        id={`school-${field}`}
                                        name={field}
                                        value={data[field]}
                                        onChange={(event) => setData(field, event.target.value)}
                                        rows={3}
                                        maxLength={2000}
                                        aria-invalid={!!errors[field]}
                                        aria-describedby={errors[field] ? `school-${field}-error` : undefined}
                                        className="border-input focus-visible:ring-ring min-h-24 w-full rounded-md border bg-transparent px-3 py-2 text-base focus-visible:ring-2 focus-visible:outline-none md:text-sm"
                                    />
                                ) : (
                                    <Input
                                        id={`school-${field}`}
                                        name={field}
                                        value={data[field]}
                                        onChange={(event) => setData(field, event.target.value)}
                                        required={field === 'name'}
                                        maxLength={field === 'deped_school_id' ? 6 : 255}
                                        inputMode={field === 'deped_school_id' ? 'numeric' : undefined}
                                        pattern={field === 'deped_school_id' ? '[0-9]{6}' : undefined}
                                        aria-invalid={!!errors[field]}
                                        aria-describedby={
                                            field === 'deped_school_id'
                                                ? `school-id-help school-${field}-error`
                                                : errors[field]
                                                  ? `school-${field}-error`
                                                  : undefined
                                        }
                                        className="min-h-11"
                                    />
                                )}
                                {field === 'deped_school_id' && (
                                    <p id="school-id-help" className="text-muted-foreground text-sm">
                                        {labels.school_id_help}
                                    </p>
                                )}
                                <div id={`school-${field}-error`}>
                                    <InputError message={errors[field]} />
                                </div>
                            </div>
                        ))}
                        <div className="flex flex-wrap items-center gap-4">
                            <Button type="submit" disabled={processing} className="min-h-11">
                                {processing ? labels.saving : labels.save}
                            </Button>
                            <p role="status" aria-live="polite" className="text-muted-foreground text-sm">
                                {recentlySuccessful ? labels.saved : ''}
                            </p>
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
