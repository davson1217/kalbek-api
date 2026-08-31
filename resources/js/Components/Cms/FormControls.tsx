import { Info } from 'lucide-react';
import { useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import type { PropsWithChildren } from 'react';

export interface Option {
    label: string;
    value: number | string;
}

export function FieldError({ message }: { message?: string }) {
    return message ? <p className="mt-1 text-xs font-medium text-rose-600">{message}</p> : null;
}

export function InputLabel({ children, help }: PropsWithChildren<{ help?: string }>) {
    return (
        <span className="flex items-center gap-1.5 text-xs font-bold uppercase tracking-[0.14em] text-slate-500">
            {children}
            {help ? <InfoPopover content={help} /> : null}
        </span>
    );
}

export function TextField({ help, label, placeholder, value, onChange, error, type = 'text' }: { help?: string; label: string; placeholder?: string; value: number | string; onChange: (value: string) => void; error?: string; type?: string }) {
    return (
        <label className="block">
            <InputLabel help={help}>{label}</InputLabel>
            <input
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                type={type}
                className="mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-cyan-500 focus:ring-4 focus:ring-cyan-100"
            />
            <FieldError message={error} />
        </label>
    );
}

export function Textarea({ help, label, placeholder, value, onChange, error, rows = 3 }: { help?: string; label: string; placeholder?: string; value: string; onChange: (value: string) => void; error?: string; rows?: number }) {
    return (
        <label className="block">
            <InputLabel help={help}>{label}</InputLabel>
            <textarea
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                rows={rows}
                className="mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-cyan-500 focus:ring-4 focus:ring-cyan-100"
            />
            <FieldError message={error} />
        </label>
    );
}

export function SelectField({ help, label, value, onChange, options, error }: { help?: string; label: string; value: number | string; onChange: (value: number | string) => void; options: Option[]; error?: string }) {
    return (
        <label className="block">
            <InputLabel help={help}>{label}</InputLabel>
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="mt-1.5 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm outline-none transition focus:border-cyan-500 focus:ring-4 focus:ring-cyan-100"
            >
                {options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
            </select>
            <FieldError message={error} />
        </label>
    );
}

export function blankOptions(options: string[], label = 'Inherit') {
    return [{ value: '', label }, ...options.map((option) => ({ value: option, label: option.toUpperCase() }))];
}

function InfoPopover({ content }: { content: string }) {
    const buttonRef = useRef<HTMLButtonElement>(null);
    const [open, setOpen] = useState(false);
    const [position, setPosition] = useState({ left: 0, top: 0 });

    function show() {
        const rect = buttonRef.current?.getBoundingClientRect();

        if (rect) {
            const width = 256;
            const padding = 12;
            const centeredLeft = rect.left + rect.width / 2 - width / 2;

            setPosition({
                left: Math.min(Math.max(centeredLeft, padding), window.innerWidth - width - padding),
                top: rect.bottom + 8,
            });
        }

        setOpen(true);
    }

    return (
        <span className="inline-flex">
            <button
                ref={buttonRef}
                type="button"
                onBlur={() => setOpen(false)}
                onFocus={show}
                onMouseEnter={show}
                onMouseLeave={() => setOpen(false)}
                className="inline-flex size-5 items-center justify-center rounded-full text-slate-400 outline-none transition hover:bg-cyan-50 hover:text-cyan-700 focus:bg-cyan-50 focus:text-cyan-700"
                aria-label={content}
            >
                <Info className="size-3.5" />
            </button>
            {open ? createPortal(
                <span
                    className="pointer-events-none fixed z-[80] w-64 rounded-2xl border border-slate-800 bg-slate-950 px-3 py-2 text-left text-xs font-medium normal-case leading-5 tracking-normal text-white shadow-2xl shadow-slate-950/20"
                    style={{ left: position.left, top: position.top }}
                >
                    {content}
                </span>,
                document.body,
            ) : null}
        </span>
    );
}
