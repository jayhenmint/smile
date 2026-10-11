import Login from '@/pages/auth/login';
import { router, type ActiveVisit } from '@inertiajs/core';
import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';

vi.mock('@/layouts/auth-layout', () => ({ default: ({ children }: { children: ReactNode }) => <main>{children}</main> }));
vi.mock('@inertiajs/react', async (importOriginal) => ({ ...(await importOriginal<typeof import('@inertiajs/react')>()), Head: () => null }));

let post: ReturnType<typeof vi.fn<typeof router.post>>;
beforeEach(() => {
    vi.stubGlobal(
        'ResizeObserver',
        class {
            observe() {
                /* Geometry is checked in the browser. */
            }
            unobserve() {
                /* No observer in jsdom. */
            }
            disconnect() {
                /* No observer in jsdom. */
            }
        },
    );
    vi.stubGlobal('route', (name: string) => (name === 'login' ? '/login' : '/forgot-password'));
    post = vi.spyOn(router, 'post').mockImplementation(() => {});
});
afterEach(() => vi.unstubAllGlobals());

async function fillLogin() {
    const user = userEvent.setup();
    render(<Login canResetPassword={true} />);
    await user.type(screen.getByLabelText('Email address'), 'fictional@example.test');
    await user.type(screen.getByLabelText('Password', { exact: true }), 'fictional-password');
    return user;
}

it('submits Remember me when the teacher checks it', async () => {
    const user = await fillLogin();
    await user.click(screen.getByRole('checkbox', { name: 'Remember me' }));
    await user.click(screen.getByRole('button', { name: 'Log in' }));
    expect(post).toHaveBeenCalledExactlyOnceWith(
        '/login',
        {
            email: 'fictional@example.test',
            password: 'fictional-password',
            remember: true,
        },
        expect.any(Object),
    );
});

it('does not request remembered login after the teacher unchecks it', async () => {
    const user = await fillLogin();
    const checkbox = screen.getByRole('checkbox', { name: 'Remember me' });
    await user.click(checkbox);
    await user.click(checkbox);
    await user.click(screen.getByRole('button', { name: 'Log in' }));
    expect(post.mock.calls[0][1]).toMatchObject({ remember: false });
    expect(checkbox).not.toBeChecked();
});

it('clears the password after a rejected login while retaining the email and remembered-login choice', async () => {
    const user = await fillLogin();
    await user.click(screen.getByRole('checkbox', { name: 'Remember me' }));
    await user.click(screen.getByRole('button', { name: 'Log in' }));
    const callbacks = post.mock.calls[0][2];
    act(() => callbacks?.onError?.({ email: 'These credentials do not match our records.' }));
    act(() => callbacks?.onFinish?.({} as ActiveVisit));
    expect(screen.getByLabelText('Password', { exact: true })).toHaveValue('');
    expect(screen.getByLabelText('Email address')).toHaveValue('fictional@example.test');
    expect(screen.getByRole('checkbox', { name: 'Remember me' })).toBeChecked();
    expect(screen.getByText('These credentials do not match our records.')).toBeVisible();
});
