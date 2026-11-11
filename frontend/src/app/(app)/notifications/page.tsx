"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Pagination } from "@/components/ui/form";
import { pageInfo } from "@/lib/pagination";
import { Card, PageHeader, StateMessage } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import { useNotificationActions, useNotifications } from "@/lib/api/hooks";

/**
 * In-app notification centre (R27.6). Lists the caller's notifications with an
 * unread count, and supports mark-one-read and mark-all-read.
 */
export default function NotificationsPage(): JSX.Element {
  const [page, setPage] = useState(1);
  const { data, isLoading, error } = useNotifications(page);
  const { read, readAll } = useNotificationActions();

  const items = data?.data ?? [];
  const unread = data?.meta?.unread_count ?? items.filter((n) => !n.read_at).length;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Notifications"
        description={unread > 0 ? `${unread} unread` : "You're all caught up."}
        action={
          <Button
            variant="secondary"
            disabled={readAll.isPending || unread === 0}
            onClick={() => void readAll.mutateAsync()}
          >
            Mark all read
          </Button>
        }
      />

      {isLoading ? (
        <StateMessage>Loading…</StateMessage>
      ) : error ? (
        <StateMessage tone="error">{errorMessage(error)}</StateMessage>
      ) : items.length === 0 ? (
        <StateMessage>No notifications.</StateMessage>
      ) : (
        <div className="space-y-3">
          {items.map((n) => (
            <Card key={n.id} className={n.read_at ? "opacity-70" : ""}>
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className="text-sm font-medium text-foreground">{n.title ?? n.type}</p>
                  {n.body ? <p className="mt-1 text-sm text-muted-foreground">{n.body}</p> : null}
                  {n.created_at ? (
                    <p className="mt-1 text-xs text-muted-foreground">
                      {new Date(n.created_at).toLocaleString()}
                    </p>
                  ) : null}
                </div>
                {!n.read_at ? (
                  <Button
                    variant="ghost"
                    disabled={read.isPending}
                    onClick={() => void read.mutateAsync(n.id)}
                  >
                    Mark read
                  </Button>
                ) : null}
              </div>
            </Card>
          ))}
        </div>
      )}

      {data ? (
        <Pagination {...pageInfo(data)} onChange={setPage} />
      ) : null}
    </div>
  );
}
