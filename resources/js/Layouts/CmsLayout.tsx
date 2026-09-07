import { Link, router, usePage } from '@inertiajs/react';
import { BookOpen, Globe2, LayoutDashboard, LogOut, Sparkles, UsersRound } from 'lucide-react';
import type { PropsWithChildren } from 'react';

import type { SharedProps } from '../types';

const nav = [
    { href: '/cms', label: 'Dashboard', icon: LayoutDashboard },
    { href: '/cms/languages', label: 'Languages', icon: Globe2 },
    { href: '/cms/scenarios', label: 'Scenarios', icon: BookOpen },
    { href: '/cms/characters', label: 'Characters', icon: UsersRound },
];

export function CmsLayout({ children }: PropsWithChildren) {
    const { auth, flash } = usePage<SharedProps>().props;
    const path = window.location.pathname;

    return (
        <main className="min-h-screen bg-[radial-gradient(circle_at_top_left,#cffafe_0,#f8fafc_34%,#eef2ff_100%)] text-slate-950">
            <aside className="fixed inset-y-0 left-0 hidden w-72 border-r border-white/70 bg-white/75 shadow-2xl shadow-slate-200/60 backdrop-blur-xl md:block">
                <div className="border-b border-slate-100 px-5 py-5">
                    <div className="flex items-center gap-3">
                        <span className="inline-flex size-11 items-center justify-center rounded-2xl bg-slate-950 text-white shadow-lg shadow-cyan-100">
                            <Sparkles className="size-5" />
                        </span>
                        <div>
                            <p className="text-xs font-bold uppercase tracking-[0.18em] text-cyan-700">Kalbek CMS</p>
                            <p className="mt-0.5 text-lg font-black tracking-tight">Content Studio</p>
                        </div>
                    </div>
                </div>
                <nav className="space-y-1 px-3 py-4">
                    {nav.map((item) => {
                        const Icon = item.icon;
                        const active = isActiveNavItem(path, item.href);

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={`flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-bold transition ${
                                    active
                                        ? 'bg-slate-950 text-white shadow-lg shadow-slate-200'
                                        : 'text-slate-600 hover:bg-white hover:text-slate-950 hover:shadow-sm'
                                }`}
                            >
                                <Icon className="size-4" />
                                {item.label}
                            </Link>
                        );
                    })}
                </nav>
            </aside>
            <section className="md:pl-72">
                <header className="sticky top-0 z-10 border-b border-white/70 bg-white/75 px-4 py-3 shadow-sm shadow-slate-200/40 backdrop-blur-xl md:px-8">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <p className="text-sm font-semibold">{auth.user?.name}</p>
                            <p className="text-xs text-slate-500">{auth.user?.email}</p>
                        </div>
                        <button
                            type="button"
                            onClick={() => router.post('/cms/logout')}
                            className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-sm font-bold text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:text-slate-950"
                        >
                            <LogOut className="size-4" />
                            Sign out
                        </button>
                    </div>
                    <div className="mt-3 flex gap-2 md:hidden">
                        {nav.map((item) => {
                            const active = isActiveNavItem(path, item.href);

                            return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={`rounded-full border px-3 py-1.5 text-xs font-bold shadow-sm ${
                                    active
                                        ? 'border-slate-950 bg-slate-950 text-white'
                                        : 'border-slate-200 bg-white text-slate-700'
                                }`}
                            >
                                {item.label}
                            </Link>
                            );
                        })}
                    </div>
                </header>
                <div className="px-4 py-6 md:px-8">
                    {flash.success ? (
                        <div className="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm">
                            {flash.success}
                        </div>
                    ) : null}
                    {children}
                </div>
            </section>
        </main>
    );
}

function isActiveNavItem(path: string, href: string): boolean {
    if (href === '/cms') return path === href;

    return path === href || path.startsWith(`${href}/`);
}

export function FieldError({ message }: { message?: string }) {
    return message ? <p className="mt-1 text-xs text-red-600">{message}</p> : null;
}

export function InputLabel({ children }: PropsWithChildren) {
    return <label className="text-xs font-semibold uppercase tracking-wide text-slate-500">{children}</label>;
}
