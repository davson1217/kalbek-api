import { Link } from '@inertiajs/react';
import type { ComponentType, PropsWithChildren } from 'react';

interface BreadcrumbItem {
    href?: string;
    label: string;
}

export function Breadcrumbs({ items }: { items: BreadcrumbItem[] }) {
    return (
        <nav className="flex flex-wrap items-center gap-2 text-sm text-slate-500">
            {items.map((item, index) => (
                <span key={`${item.label}-${index}`} className="inline-flex items-center gap-2">
                    {index > 0 ? <span className="text-slate-300">/</span> : null}
                    {item.href ? <Link href={item.href} className="transition hover:text-slate-950">{item.label}</Link> : <span className="font-medium text-slate-800">{item.label}</span>}
                </span>
            ))}
        </nav>
    );
}

export function PageHeader({ actions, eyebrow, title, children }: PropsWithChildren<{ actions?: React.ReactNode; eyebrow?: string; title: string }>) {
    return (
        <header className="rounded-3xl border border-white/80 bg-white/80 p-5 shadow-xl shadow-slate-200/60 backdrop-blur md:p-6">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    {eyebrow ? <p className="text-xs font-bold uppercase tracking-[0.18em] text-cyan-700">{eyebrow}</p> : null}
                    <h1 className="mt-1 text-3xl font-black tracking-tight text-slate-950 md:text-4xl">{title}</h1>
                    {children ? <div className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{children}</div> : null}
                </div>
                {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
            </div>
        </header>
    );
}

export function PrimaryButton({ children, icon: Icon, type = 'button', ...props }: PropsWithChildren<{ icon?: ComponentType<{ className?: string }>; type?: 'button' | 'submit' } & React.ButtonHTMLAttributes<HTMLButtonElement>>) {
    return (
        <button type={type} {...props} className={`inline-flex items-center justify-center gap-2 rounded-full bg-slate-950 px-4 py-2 text-sm font-bold text-white shadow-lg shadow-slate-300 transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:translate-y-0 disabled:opacity-60 ${props.className ?? ''}`}>
            {Icon ? <Icon className="size-4" /> : null}
            {children}
        </button>
    );
}

export function SecondaryButton({ children, icon: Icon, type = 'button', ...props }: PropsWithChildren<{ icon?: ComponentType<{ className?: string }>; type?: 'button' | 'submit' } & React.ButtonHTMLAttributes<HTMLButtonElement>>) {
    return (
        <button type={type} {...props} className={`inline-flex items-center justify-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:border-cyan-200 hover:text-slate-950 disabled:translate-y-0 disabled:opacity-60 ${props.className ?? ''}`}>
            {Icon ? <Icon className="size-4" /> : null}
            {children}
        </button>
    );
}

export function StatusBadge({ children, tone = 'slate' }: PropsWithChildren<{ tone?: 'cyan' | 'emerald' | 'rose' | 'slate' | 'violet' }>) {
    const tones = {
        cyan: 'bg-cyan-50 text-cyan-800 ring-cyan-100',
        emerald: 'bg-emerald-50 text-emerald-800 ring-emerald-100',
        rose: 'bg-rose-50 text-rose-800 ring-rose-100',
        slate: 'bg-slate-100 text-slate-700 ring-slate-200',
        violet: 'bg-violet-50 text-violet-800 ring-violet-100',
    };

    return <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold ring-1 ${tones[tone]}`}>{children}</span>;
}
