import { Head, useForm } from '@inertiajs/react';

export default function Login() {
    const form = useForm({ email: '', password: '', remember: false });

    return (
        <main className="flex min-h-screen items-center justify-center bg-slate-100 px-4 text-slate-950">
            <Head title="CMS Login" />
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/cms/login');
                }}
                className="w-full max-w-sm rounded-lg border border-slate-200 bg-white p-6 shadow-sm"
            >
                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Kalbek CMS
                </p>
                <h1 className="mt-1 text-2xl font-semibold">Sign in</h1>
                <label className="mt-5 block text-sm font-medium">
                    Email
                    <input
                        value={form.data.email}
                        onChange={(event) => form.setData('email', event.target.value)}
                        type="email"
                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                    />
                </label>
                {form.errors.email ? <p className="mt-1 text-xs text-red-600">{form.errors.email}</p> : null}
                <label className="mt-4 block text-sm font-medium">
                    Password
                    <input
                        value={form.data.password}
                        onChange={(event) => form.setData('password', event.target.value)}
                        type="password"
                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                    />
                </label>
                {form.errors.password ? (
                    <p className="mt-1 text-xs text-red-600">{form.errors.password}</p>
                ) : null}
                <label className="mt-4 flex items-center gap-2 text-sm">
                    <input
                        checked={form.data.remember}
                        onChange={(event) => form.setData('remember', event.target.checked)}
                        type="checkbox"
                    />
                    Remember me
                </label>
                <button
                    disabled={form.processing}
                    type="submit"
                    className="mt-5 w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                >
                    Sign in
                </button>
            </form>
        </main>
    );
}
