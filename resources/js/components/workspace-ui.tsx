import { cn } from '@/lib/utils';
import { type LucideIcon } from 'lucide-react';
import { type ReactNode } from 'react';

export function WorkspaceCard({ children, className }: { children: ReactNode; className?: string }) {
    return <section className={cn('bg-card rounded-2xl border p-5 shadow-[0_3px_10px_rgba(23,34,59,0.02)] sm:p-6', className)}>{children}</section>;
}

export function WorkspaceStat({
    label,
    detail,
    icon: Icon,
    tone = 'text-primary',
}: {
    label: string;
    detail: string;
    icon: LucideIcon;
    tone?: string;
}) {
    return (
        <WorkspaceCard>
            <div className="flex items-center justify-between gap-3">
                <h2 className="text-muted-foreground text-sm">{label}</h2>
                <Icon className={cn('size-5', tone)} aria-hidden="true" />
            </div>
            <p className="mt-4 text-3xl font-semibold tracking-tight" aria-label={detail}>
                —
            </p>
            <p className="text-muted-foreground mt-2 text-xs">{detail}</p>
        </WorkspaceCard>
    );
}

export function WorkspaceEmpty({ icon: Icon, title, detail, children }: { icon: LucideIcon; title: string; detail: string; children?: ReactNode }) {
    return (
        <div className="flex flex-col items-center justify-center px-4 py-10 text-center">
            <span className="bg-primary/8 text-primary mb-4 flex size-12 items-center justify-center rounded-2xl">
                <Icon className="size-6" aria-hidden="true" />
            </span>
            <h3 className="text-sm font-medium">{title}</h3>
            <p className="text-muted-foreground mt-2 max-w-sm text-xs leading-6">{detail}</p>
            {children && <div className="mt-5">{children}</div>}
        </div>
    );
}
