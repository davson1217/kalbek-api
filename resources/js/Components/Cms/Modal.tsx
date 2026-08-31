import { X } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { createPortal } from 'react-dom';

export function Modal({ children, description, onClose, open, title }: PropsWithChildren<{ description?: string; onClose: () => void; open: boolean; title: string }>) {
    if (!open) {
        return null;
    }

    const modal = (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 px-4 py-6 backdrop-blur-sm" role="dialog" aria-modal="true">
            <button type="button" className="absolute inset-0 cursor-default" aria-label="Close modal" onClick={onClose} />
            <section className="relative max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-white/70 bg-white p-5 shadow-2xl shadow-slate-950/20 animate-in fade-in zoom-in-95 duration-200">
                <div className="flex items-start justify-between gap-4 border-b border-slate-100 pb-4">
                    <div>
                        <h2 className="text-xl font-bold tracking-tight text-slate-950">{title}</h2>
                        {description ? <p className="mt-1 text-sm text-slate-500">{description}</p> : null}
                    </div>
                    <button type="button" onClick={onClose} className="rounded-full border border-slate-200 p-2 text-slate-500 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900">
                        <X className="size-4" />
                    </button>
                </div>
                <div className="pt-5">{children}</div>
            </section>
        </div>
    );

    return createPortal(modal, document.body);
}
