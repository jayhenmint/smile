import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Section = {
    id: number;
    name: string;
    school_year_id: number;
    school_year_label: string;
    adviser_id: string | null;
    adviser_name: string | null;
    grade_level: string;
    placement_type: string;
};
type Choice = { id: number | string; name?: string; label?: string };
type Sections = { data: Section[]; prev_page_url: string | null; next_page_url: string | null };
const selectClass =
    'border-input focus-visible:ring-ring min-h-11 w-full rounded-md border bg-background px-3 py-2 text-base focus-visible:ring-2 focus-visible:outline-none md:text-sm';

function SectionForm({
    section,
    schoolYears,
    advisers,
    labels,
}: {
    section?: Section;
    schoolYears: Choice[];
    advisers: Choice[];
    labels: Record<string, string>;
}) {
    const { data, setData, transform, post, patch, reset, errors, processing, recentlySuccessful } = useForm({
        name: section?.name ?? '',
        school_year_id: section?.school_year_id.toString() ?? '',
        adviser_id: section?.adviser_id ?? '',
        placement_type: section?.placement_type ?? 'sped_self_contained',
    });
    const prefix = `section-${section?.id ?? 'new'}`;
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        transform((values) => (section ? { name: values.name, adviser_id: values.adviser_id, placement_type: values.placement_type } : values));
        if (section) {
            patch(route('sections.update', { section: section.id }), { preserveScroll: true });
        } else {
            post(route('sections.store'), { preserveScroll: true, onSuccess: () => reset() });
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4" aria-label={section?.name ?? labels.create_title}>
            {section ? (
                <p className="text-sm">
                    {labels.school_year_id}: {section.school_year_label}
                </p>
            ) : (
                <div className="grid gap-2">
                    <Label htmlFor={`${prefix}-year`}>{labels.school_year_id}</Label>
                    <select
                        id={`${prefix}-year`}
                        value={data.school_year_id}
                        onChange={(event) => setData('school_year_id', event.target.value)}
                        required
                        className={selectClass}
                        aria-invalid={!!errors.school_year_id}
                        aria-describedby={`${prefix}-year-error`}
                    >
                        <option value="">{labels.choose_year}</option>
                        {schoolYears.map((year) => (
                            <option key={year.id} value={year.id}>
                                {year.label}
                            </option>
                        ))}
                    </select>
                    <div id={`${prefix}-year-error`}>
                        <InputError message={errors.school_year_id} />
                    </div>
                </div>
            )}
            <p className="text-muted-foreground text-sm">{labels.year_help}</p>
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-name`}>{labels.name}</Label>
                <Input
                    id={`${prefix}-name`}
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    required
                    maxLength={255}
                    className="min-h-11"
                    aria-invalid={!!errors.name}
                    aria-describedby={`${prefix}-name-error`}
                />
                <div id={`${prefix}-name-error`}>
                    <InputError message={errors.name} />
                </div>
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-placement`}>{labels.placement_type}</Label>
                <select
                    id={`${prefix}-placement`}
                    value={data.placement_type}
                    onChange={(event) => setData('placement_type', event.target.value)}
                    required
                    className={selectClass}
                    aria-invalid={!!errors.placement_type}
                    aria-describedby={`${prefix}-placement-error`}
                >
                    {['sped_self_contained', 'resource_room', 'regular'].map((placement) => (
                        <option key={placement} value={placement}>
                            {labels[placement]}
                        </option>
                    ))}
                </select>
                <div id={`${prefix}-placement-error`}>
                    <InputError message={errors.placement_type} />
                </div>
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-adviser`}>{labels.adviser_id}</Label>
                <select
                    id={`${prefix}-adviser`}
                    value={data.adviser_id}
                    onChange={(event) => setData('adviser_id', event.target.value)}
                    className={selectClass}
                    aria-invalid={!!errors.adviser_id}
                    aria-describedby={`${prefix}-adviser-error ${prefix}-adviser-help`}
                >
                    <option value="">{labels.unassigned}</option>
                    {section?.adviser_id && !advisers.some((adviser) => adviser.id === section.adviser_id) && (
                        <option value={section.adviser_id} disabled>
                            {section.adviser_name} ({labels.unavailable})
                        </option>
                    )}
                    {advisers.map((adviser) => (
                        <option key={adviser.id} value={adviser.id}>
                            {adviser.name}
                        </option>
                    ))}
                </select>
                <p id={`${prefix}-adviser-help`} className="text-muted-foreground text-sm">
                    {labels.adviser_help}
                </p>
                <div id={`${prefix}-adviser-error`}>
                    <InputError message={errors.adviser_id} />
                </div>
            </div>
            <div className="flex flex-wrap items-center gap-3">
                <Button type="submit" disabled={processing || (!section && schoolYears.length === 0)} className="min-h-11">
                    {section ? (processing ? labels.saving : labels.save) : processing ? labels.creating : labels.create}
                </Button>
                <p role="status" aria-live="polite" className="text-muted-foreground text-sm">
                    {recentlySuccessful ? (section ? labels.saved : labels.created) : ''}
                </p>
            </div>
        </form>
    );
}

export default function SectionsPage({
    sections,
    schoolYears,
    advisers,
    canManage,
    labels,
}: {
    sections: Sections;
    schoolYears: Choice[];
    advisers: Choice[];
    canManage: boolean;
    labels: Record<string, string>;
}) {
    return (
        <AppLayout breadcrumbs={[{ title: labels.title, href: '/settings/sections' }]}>
            <Head title={`${labels.title} | SMILE`} />
            <SettingsLayout>
                {canManage && (
                    <div className="space-y-6">
                        <HeadingSmall title={labels.create_title} description={labels.description} />
                        {schoolYears.length === 0 && (
                            <p className="text-sm">
                                {labels.years_required}{' '}
                                <Link href={route('school-years.index')} className="underline">
                                    {labels.school_years}
                                </Link>
                            </p>
                        )}
                        <SectionForm schoolYears={schoolYears} advisers={advisers} labels={labels} />
                    </div>
                )}
                <div className="space-y-4">
                    <HeadingSmall title={labels.list_title} />
                    <ul className="space-y-4">
                        {sections.data.map((section) => (
                            <li key={section.id} className="space-y-4 rounded-md border p-4">
                                <p className="font-medium break-words">{section.name}</p>
                                <p className="text-sm">{labels.non_graded}</p>
                                {canManage ? (
                                    <SectionForm
                                        key={`${section.id}-${section.name}-${section.adviser_id}-${section.placement_type}`}
                                        section={section}
                                        schoolYears={schoolYears}
                                        advisers={advisers}
                                        labels={labels}
                                    />
                                ) : (
                                    <div className="space-y-1 text-sm">
                                        <p>
                                            {labels.school_year_id}: {section.school_year_label}
                                        </p>
                                        <p>
                                            {labels.placement_type}: {labels[section.placement_type]}
                                        </p>
                                        <p>
                                            {labels.adviser_id}: {section.adviser_name ?? labels.unassigned}
                                        </p>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                    {sections.data.length === 0 && <p className="text-muted-foreground text-sm">{labels.empty}</p>}
                    <div className="flex gap-3">
                        {sections.prev_page_url && (
                            <Button variant="outline" asChild className="min-h-11">
                                <Link href={sections.prev_page_url}>{labels.previous}</Link>
                            </Button>
                        )}
                        {sections.next_page_url && (
                            <Button variant="outline" asChild className="min-h-11">
                                <Link href={sections.next_page_url}>{labels.next}</Link>
                            </Button>
                        )}
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
