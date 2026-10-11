import Documents from '@/pages/learners/documents';
import { router, type Page, type PendingVisit, type VisitOptions } from '@inertiajs/core';
import { act, fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ComponentProps, ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

// Keep the real Inertia form hook and UI controls; isolate the workspace shell
// and network boundary so no test needs a login or a database connection.
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: ReactNode }) => <main>{children}</main> }));
vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
}));

const text = {
    title: 'Document checklist',
    learners: 'Learners',
    profile: 'Learner profile',
    hint: 'Missing documents do not block enrollment.',
    scope_hint: 'Only your advised learners.',
    paper_hint: 'Attachments are optional.',
    missing: 'Missing',
    submitted: 'Submitted',
    verified: 'Verified',
    expired: 'Expired',
    enrollment: 'Enrollment',
    parent: 'From parent',
    status: 'Status',
    submitted_on: 'Date submitted',
    notes: 'Teacher notes (optional)',
    edit: 'Update checklist',
    save: 'Save status',
    cancel: 'Cancel',
    file: 'Attachment',
    upload: 'Upload attachment',
    download: 'Download attachment',
    download_hint: 'Refresh to renew expired links.',
    record_submission: 'Record submission before attaching a file.',
    file_hint: 'PDF, JPEG or PNG. Maximum size (MB):',
    saved: 'Checklist updated.',
    uploaded: 'Private attachment uploaded.',
};
type Item = ComponentProps<typeof Documents>['items'][number];
const item: Item = {
    id: 2,
    name: 'Birth certificate',
    required_for: 'enrollment',
    from_party: 'parent',
    status: 'submitted',
    submitted_on: '2026-10-10',
    notes: null,
    record_id: 27,
    has_file: false,
    download_url: null,
};
const learner = { id: '00000000-0000-4000-8000-000000000001', name: 'Fictional Learner', smile_id: 'SM-23456789' };
const url = `/learners/${learner.id}/documents`;
const page: Page = {
    component: 'learners/documents',
    props: { errors: {} },
    url,
    version: null,
    clearHistory: false,
    encryptHistory: false,
    rememberedState: {},
};

function show(items: Item[] = [item]) {
    return render(<Documents learner={learner} items={items} text={text} saved={false} uploaded={false} maxUploadKb={2048} />);
}

let post: ReturnType<typeof vi.fn<typeof router.post>>;
let put: ReturnType<typeof vi.fn<typeof router.put>>;
beforeEach(() => {
    post = vi.spyOn(router, 'post').mockImplementation(() => {});
    put = vi.spyOn(router, 'put').mockImplementation(() => {});
});

function optionsFor(spy: typeof post | typeof put): VisitOptions {
    const options = spy.mock.calls.at(-1)?.[2];
    if (!options) throw new Error('Expected a form submission');
    return options;
}

// jsdom 26 does not validate a user-event synthetic FileList as a native file.
// Dispatch submit explicitly; real-browser native validation remains a manual check.
describe('document attachment flow', () => {
    it.each([
        { ...item, status: 'missing', submitted_on: null },
        { ...item, record_id: null },
    ])('requires a recorded non-missing submission before offering upload ($status / $record_id)', (pendingItem) => {
        show([pendingItem]);
        expect(screen.getByText(text.record_submission)).toBeVisible();
        expect(screen.queryByLabelText(text.file)).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: text.upload })).not.toBeInTheDocument();
        expect(post).not.toHaveBeenCalled();
    });

    it('submits the selected file to its nested record and prevents repeat uploads while processing', async () => {
        const user = userEvent.setup();
        show();
        const input = screen.getByLabelText<HTMLInputElement>(text.file);
        const button = screen.getByRole('button', { name: text.upload });
        expect(button).toBeDisabled();
        expect(input).toHaveAccessibleDescription(`${text.file_hint} 2`);
        const file = new File(['fictional attachment'], 'sample.pdf', { type: 'application/pdf' });
        await user.upload(input, file);
        expect(button).toBeEnabled();
        fireEvent.submit(input.form!);
        expect(post).toHaveBeenCalledExactlyOnceWith(
            `${url}/files/27`,
            { file },
            expect.objectContaining({ forceFormData: true, preserveScroll: true }),
        );
        const callbacks = optionsFor(post);
        // Simulate router lifecycle notifications, leaving form state to useForm.
        act(() => callbacks.onStart?.({} as PendingVisit));
        act(() => callbacks.onProgress?.({ loaded: 1, total: 2, bytes: 1, lengthComputable: true, percentage: 50 }));
        expect(input).toBeDisabled();
        expect(button).toBeDisabled();
        expect(screen.getByRole('progressbar', { name: text.upload })).toHaveAttribute('value', '50');
        await user.click(button);
        expect(post).toHaveBeenCalledTimes(1);
    });

    it('keeps a rejected file selected, exposes its error accessibly, and lets the teacher retry', async () => {
        const user = userEvent.setup();
        show();
        const input = screen.getByLabelText<HTMLInputElement>(text.file);
        const file = new File(['fictional attachment'], 'sample.pdf', { type: 'application/pdf' });
        await user.upload(input, file);
        fireEvent.submit(input.form!);
        act(() => optionsFor(post).onError?.({ file: 'The attachment could not be saved. Please try again.' }));
        expect(input).toHaveAttribute('aria-invalid', 'true');
        expect(input).toHaveAccessibleDescription(`${text.file_hint} 2 The attachment could not be saved. Please try again.`);
        expect(input.files?.[0]).toBe(file);
        expect(input).toBeEnabled();
        fireEvent.submit(input.form!);
        expect(post).toHaveBeenCalledTimes(2);
        expect(post.mock.calls[1][1]).toEqual({ file });
    });

    it('clears both the native file selection and form data after success', async () => {
        const user = userEvent.setup();
        show();
        const input = screen.getByLabelText<HTMLInputElement>(text.file);
        await user.upload(input, new File(['fictional attachment'], 'sample.png', { type: 'image/png' }));
        fireEvent.submit(input.form!);
        await act(async () => {
            await optionsFor(post).onSuccess?.(page);
        });
        expect(input).toHaveValue('');
        expect(input.files).toHaveLength(0);
        expect(screen.getByRole('button', { name: text.upload })).toBeDisabled();
    });

    it('renders the signed attachment URL as a normal download link with no replacement input', () => {
        const signedUrl = `${url}/files/27?user=fake-user&expires=123&signature=fake-signature`;
        show([{ ...item, has_file: true, download_url: signedUrl }]);
        expect(screen.getByRole('link', { name: text.download })).toHaveAttribute('href', signedUrl);
        expect(screen.getByText(text.download_hint)).toBeVisible();
        expect(screen.queryByLabelText(text.file)).not.toBeInTheDocument();
        expect(post).not.toHaveBeenCalled();
    });
});

describe('document checklist corrections', () => {
    it('clears the submission date when changed to Missing and saves the selected document type', async () => {
        const user = userEvent.setup();
        show();
        await user.click(screen.getByRole('button', { name: text.edit }));
        expect(screen.getByLabelText(text.submitted_on)).toHaveValue('2026-10-10');
        await user.selectOptions(screen.getByLabelText(text.status), 'missing');
        expect(screen.queryByLabelText(text.submitted_on)).not.toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: text.save }));
        expect(put).toHaveBeenCalledExactlyOnceWith(
            `${url}/2`,
            { status: 'missing', submitted_on: '', notes: '' },
            expect.objectContaining({ preserveScroll: true }),
        );
        await user.selectOptions(screen.getByLabelText(text.status), 'submitted');
        expect(screen.getByLabelText(text.submitted_on)).toHaveValue('');
    });

    it('retains rejected checklist values, then discards them and errors on Cancel', async () => {
        const user = userEvent.setup();
        show([{ ...item, notes: 'Original fictional note' }]);
        await user.click(screen.getByRole('button', { name: text.edit }));
        await user.clear(screen.getByLabelText(text.notes));
        await user.type(screen.getByLabelText(text.notes), 'Changed fictional note');
        fireEvent.change(screen.getByLabelText(text.submitted_on), { target: { value: '2026-10-11' } });
        await user.click(screen.getByRole('button', { name: text.save }));
        act(() => optionsFor(put).onError?.({ submitted_on: 'Date is unavailable.' }));
        expect(screen.getByLabelText(text.submitted_on)).toHaveValue('2026-10-11');
        expect(screen.getByLabelText(text.submitted_on)).toHaveAccessibleDescription('Date is unavailable.');
        expect(screen.getByLabelText(text.notes)).toHaveValue('Changed fictional note');
        await user.click(screen.getByRole('button', { name: text.cancel }));
        await user.click(screen.getByRole('button', { name: text.edit }));
        expect(screen.getByLabelText(text.submitted_on)).toHaveValue('2026-10-10');
        expect(screen.getByLabelText(text.notes)).toHaveValue('Original fictional note');
        expect(screen.queryByText('Date is unavailable.')).not.toBeInTheDocument();
    });

    it('keeps the attachment draft when a checklist correction succeeds', async () => {
        const user = userEvent.setup();
        show();
        const input = screen.getByLabelText<HTMLInputElement>(text.file);
        const file = new File(['fictional attachment'], 'sample.pdf', { type: 'application/pdf' });
        await user.upload(input, file);
        await user.click(screen.getByRole('button', { name: text.edit }));
        await user.type(screen.getByLabelText(text.notes), 'Paper accepted');
        await user.click(screen.getByRole('button', { name: text.save }));
        await act(async () => {
            await optionsFor(put).onSuccess?.(page);
        });
        expect(screen.queryByLabelText(text.notes)).not.toBeInTheDocument();
        expect(input.files?.[0]).toBe(file);
        fireEvent.submit(input.form!);
        expect(post.mock.calls[0][1]).toEqual({ file });
    });
});
