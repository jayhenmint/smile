import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { WorkspaceCard } from '@/components/workspace-ui';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { Head, Link } from '@inertiajs/react';

type Props = {
    entries: {
        data: { id: number; occurred_at: string | null; action: string; smile_id: string; actor: string }[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    text: Record<string, string>;
};

export default function AuditLog({ entries, text: t }: Props) {
    return (
        <AppLayout breadcrumbs={[{ title: t.title, href: '/settings/audit-log' }]}>
            <Head title={t.title} />
            <SettingsLayout>
                <div className="space-y-5">
                    <HeadingSmall title={t.title} description={t.hint} />
                    {entries.data.length === 0 && (
                        <p role="status" className="text-muted-foreground text-sm">
                            {t.empty}
                        </p>
                    )}
                    {entries.data.map((entry) => (
                        <WorkspaceCard key={entry.id}>
                            <h2 className="font-semibold">{entry.action}</h2>
                            <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                <div>
                                    <dt className="text-muted-foreground">{t.learner}</dt>
                                    <dd className="mt-1 font-medium">{entry.smile_id}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">{t.actor}</dt>
                                    <dd className="mt-1">{entry.actor}</dd>
                                </div>
                                <div className="sm:col-span-2">
                                    <dt className="text-muted-foreground">{t.occurred_at}</dt>
                                    <dd className="mt-1">
                                        {entry.occurred_at && (
                                            <time dateTime={entry.occurred_at}>{new Date(entry.occurred_at).toLocaleString()}</time>
                                        )}
                                    </dd>
                                </div>
                            </dl>
                        </WorkspaceCard>
                    ))}
                    <div className="flex gap-3">
                        {entries.prev_page_url && (
                            <Button asChild variant="outline" className="min-h-11">
                                <Link href={entries.prev_page_url}>{t.previous}</Link>
                            </Button>
                        )}
                        {entries.next_page_url && (
                            <Button asChild variant="outline" className="min-h-11">
                                <Link href={entries.next_page_url}>{t.next}</Link>
                            </Button>
                        )}
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
