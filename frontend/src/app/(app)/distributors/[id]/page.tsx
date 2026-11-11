"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useState } from "react";

import { useAlerts } from "@/components/providers/alerts-provider";
import { useAuth } from "@/components/providers/auth-provider";
import { useImpersonation } from "@/components/providers/impersonation-provider";
import { Button, buttonClasses } from "@/components/ui/button";
import { DataTable, type Column } from "@/components/ui/data-table";
import { Field, Pagination } from "@/components/ui/form";
import { pageInfo } from "@/lib/pagination";
import { Icon } from "@/components/ui/icons";
import { Modal, ModalFormFooter } from "@/components/ui/modal";
import { PageHeader } from "@/components/ui/primitives";
import { errorMessage } from "@/lib/api/errors";
import {
  useCreateDealerUser,
  useCreateDistributorUser,
  useDealers,
  useDealerUsers,
  useDistributor,
  useDistributorUsers,
  useResetDealerUserPassword,
  useResetDistributorUserPassword,
} from "@/lib/api/hooks";
import type { Dealer, TenantUser } from "@/lib/api/types";

/**
 * Dealers under a distributor (R10.2). Lists the distributor's dealers and,
 * for managers (dealers.manage), offers an "Add dealer" button that opens the
 * full create page. The per-distributor dealer quota (R10.4) is enforced
 * server-side; a 403 surfaces as a form error on the create page.
 */
export default function DistributorDealersPage(): JSX.Element {
  const params = useParams<{ id: string }>();
  const distributorId = Number(params.id);
  const { can } = useAuth();
  const router = useRouter();
  const { start } = useImpersonation();
  const { toast } = useAlerts();
  const [page, setPage] = useState(1);
  const [busyId, setBusyId] = useState<number | null>(null);
  const [impError, setImpError] = useState<string | null>(null);
  const { data, isLoading, error } = useDealers(distributorId, page);
  const { data: distributor } = useDistributor(distributorId);

  // Company (and the owning distributor) may impersonate a dealer to order for it.
  const canImpersonate = can("orders.create");
  const canManageUsers = can("users.manage");

  // The "Add user" modal target: the distributor itself, or one of its dealers.
  const [userTarget, setUserTarget] = useState<
    | { kind: "distributor"; name: string }
    | { kind: "dealer"; dealer: Dealer }
    | null
  >(null);

  async function impersonate(d: Dealer): Promise<void> {
    setImpError(null);
    setBusyId(d.id);
    try {
      await start("dealer", d.id);
      toast({ title: "Impersonation started", message: `Now acting as ${d.name}.`, tone: "info" });
      router.push("/orders/cart");
    } catch (err) {
      setImpError(errorMessage(err));
      toast({ title: "Could not impersonate", message: errorMessage(err), tone: "danger" });
    } finally {
      setBusyId(null);
    }
  }

  const columns: Column<Dealer>[] = [
    { header: "Name", cell: (d) => <span className="font-medium">{d.name}</span> },
    { header: "Email", cell: (d) => d.contact_email ?? "—" },
    { header: "GSTIN", cell: (d) => d.gstin ?? "—" },
    ...(can("dealers.manage") || canImpersonate || canManageUsers
      ? [
          {
            header: "Actions",
            className: "text-right",
            cell: (d: Dealer) => (
              <div className="flex justify-end gap-1">
                {canImpersonate ? (
                  <Button
                    size="sm"
                    variant="outline"
                    disabled={busyId === d.id}
                    onClick={() => void impersonate(d)}
                    title={`Act as ${d.name}`}
                  >
                    <Icon.users size={14} />
                    {busyId === d.id ? "…" : "Act as"}
                  </Button>
                ) : null}
                {canManageUsers ? (
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() => setUserTarget({ kind: "dealer", dealer: d })}
                    title={`Manage login users for ${d.name}`}
                  >
                    <Icon.users size={14} /> Users
                  </Button>
                ) : null}
                {can("dealers.manage") ? (
                  <Link
                    href={`/distributors/${distributorId}/dealers/${d.id}/edit`}
                    aria-label={`Edit ${d.name}`}
                    title="Edit"
                    className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground shadow-sm transition-colors hover:bg-muted hover:text-foreground"
                  >
                    <Icon.pencil size={16} />
                  </Link>
                ) : null}
              </div>
            ),
          } as Column<Dealer>,
        ]
      : []),
  ];

  return (
    <div className="space-y-6">
      <PageHeader
        title="Dealers"
        description="Dealers served by this distributor."
        action={
          <div className="flex items-center gap-2">
            <Link href="/distributors" className={buttonClasses("outline")}>
              <Icon.chevronLeft size={16} /> Distributors
            </Link>
            {canManageUsers ? (
              <Button
                variant="outline"
                onClick={() =>
                  setUserTarget({
                    kind: "distributor",
                    name: distributor?.data.name ?? "distributor",
                  })
                }
              >
                <Icon.users size={16} /> Manage users
              </Button>
            ) : null}
            {can("dealers.manage") ? (
              <Link
                href={`/distributors/${distributorId}/dealers/new`}
                className={buttonClasses("primary")}
              >
                <Icon.plus size={16} /> Add dealer
              </Link>
            ) : null}
          </div>
        }
      />

      {impError ? (
        <p className="text-sm text-danger" role="alert">
          {impError}
        </p>
      ) : null}

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(d) => d.id}
        isLoading={isLoading}
        error={error ? errorMessage(error) : null}
        emptyMessage="No dealers yet."
      />

      {data ? (
        <Pagination {...pageInfo(data)} onChange={setPage} />
      ) : null}

      {userTarget ? (
        <PartyUsersModal
          distributorId={distributorId}
          target={userTarget}
          onClose={() => setUserTarget(null)}
        />
      ) : null}
    </div>
  );
}

/**
 * Manage the login users for a party's portal (R8.6). Lists the Distributor's
 * or a Dealer's existing panel users and offers an inline "Add user" form to
 * create a new login (name + email + password). Creation is gated server-side
 * by `users.manage` + `subscription.active` and the tenant `max_users` quota;
 * a 403 surfaces as a toast. These users can log in to their own portal and see
 * their scoped order history.
 */
function PartyUsersModal({
  distributorId,
  target,
  onClose,
}: {
  distributorId: number;
  target:
    | { kind: "distributor"; name: string }
    | { kind: "dealer"; dealer: Dealer };
  onClose: () => void;
}): JSX.Element {
  const { toast } = useAlerts();
  const isDealer = target.kind === "dealer";
  const dealerId = isDealer ? target.dealer.id : null;
  const partyName = isDealer ? target.dealer.name : target.name;

  const distUsers = useDistributorUsers(isDealer ? null : distributorId);
  const dealerUsers = useDealerUsers(isDealer ? distributorId : null, dealerId);
  const list = isDealer ? dealerUsers : distUsers;

  const createDistUser = useCreateDistributorUser(distributorId);
  const createDealerUser = useCreateDealerUser(distributorId, dealerId ?? 0);
  const resetDistUser = useResetDistributorUserPassword(distributorId);
  const resetDealerUser = useResetDealerUserPassword(distributorId, dealerId ?? 0);

  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [resetTarget, setResetTarget] = useState<TenantUser | null>(null);

  const busy = createDistUser.isPending || createDealerUser.isPending;
  const resetting = resetDistUser.isPending || resetDealerUser.isPending;
  const formId = "party-user-form";

  async function resetPassword(newPassword: string): Promise<void> {
    if (!resetTarget) return;
    try {
      if (isDealer) {
        await resetDealerUser.mutateAsync({ userId: resetTarget.id, password: newPassword });
      } else {
        await resetDistUser.mutateAsync({ userId: resetTarget.id, password: newPassword });
      }
      toast({
        title: "Password updated",
        message: `${resetTarget.name} can now log in with the new password.`,
        tone: "success",
      });
      setResetTarget(null);
    } catch (err) {
      toast({ title: "Could not reset password", message: errorMessage(err), tone: "danger" });
    }
  }

  async function submit(e: React.FormEvent): Promise<void> {
    e.preventDefault();
    const payload = { name, email, password };
    try {
      if (isDealer) {
        await createDealerUser.mutateAsync(payload);
      } else {
        await createDistUser.mutateAsync(payload);
      }
      toast({
        title: "User created",
        message: `${name} can now log in to the ${partyName} portal.`,
        tone: "success",
      });
      setName("");
      setEmail("");
      setPassword("");
    } catch (err) {
      toast({ title: "Could not create user", message: errorMessage(err), tone: "danger" });
    }
  }

  const columns: Column<TenantUser>[] = [
    { header: "Name", cell: (u) => <span className="font-medium">{u.name}</span> },
    { header: "Email", cell: (u) => u.email },
    {
      header: "",
      className: "text-right",
      cell: (u) => (
        <Button
          size="sm"
          variant="outline"
          disabled={resetting}
          onClick={() => setResetTarget(u)}
          title={`Reset password for ${u.name}`}
        >
          <Icon.key size={14} /> Reset password
        </Button>
      ),
    },
  ];

  return (
    <>
    <Modal
      open
      onClose={onClose}
      size="lg"
      title={`Portal users — ${partyName}`}
      description="People who can log in to this portal and view its order history and statuses."
    >
      <div className="space-y-5">
        <DataTable
          columns={columns}
          rows={list.data?.data ?? []}
          rowKey={(u) => u.id}
          isLoading={list.isLoading}
          error={list.error ? errorMessage(list.error) : null}
          emptyMessage="No login users yet."
        />

        <form id={formId} onSubmit={submit} className="space-y-4 border-t border-border pt-4">
          <h3 className="text-sm font-semibold text-foreground">Add a login user</h3>
          <div className="grid gap-4 sm:grid-cols-3">
            <Field label="Name" value={name} onChange={setName} required />
            <Field label="Email" value={email} onChange={setEmail} type="email" required />
            <Field label="Password" value={password} onChange={setPassword} type="password" required />
          </div>
        </form>
      </div>

      <div className="mt-4 flex justify-end gap-2 border-t border-border pt-4">
        <ModalFormFooter
          formId={formId}
          submitLabel="Create user"
          busy={busy}
          onCancel={onClose}
          disabled={!name || !email || !password}
        />
      </div>
    </Modal>

    {resetTarget ? (
      <ResetPasswordModal
        user={resetTarget}
        busy={resetting}
        onClose={() => setResetTarget(null)}
        onSubmit={resetPassword}
      />
    ) : null}
    </>
  );
}

/**
 * Admin password reset for a single portal user (R7.3). Collects a new password
 * (and a confirmation that must match) and delegates to the parent, which calls
 * the gated reset endpoint. There is no "current password" — this is an
 * admin-initiated reset, not a self-service change.
 */
function ResetPasswordModal({
  user,
  busy,
  onClose,
  onSubmit,
}: {
  user: TenantUser;
  busy: boolean;
  onClose: () => void;
  onSubmit: (password: string) => Promise<void>;
}): JSX.Element {
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const formId = "reset-password-form";
  const mismatch = confirm.length > 0 && password !== confirm;
  const valid = password.length > 0 && password === confirm;

  return (
    <Modal
      open
      onClose={onClose}
      title={`Reset password — ${user.name}`}
      description={`Set a new login password for ${user.email}. They will use it on their next sign-in.`}
      footer={
        <ModalFormFooter
          formId={formId}
          submitLabel="Reset password"
          busy={busy}
          onCancel={onClose}
          disabled={!valid}
        />
      }
    >
      <form
        id={formId}
        onSubmit={(e) => {
          e.preventDefault();
          if (valid) void onSubmit(password);
        }}
        className="space-y-4"
      >
        <Field label="New password" value={password} onChange={setPassword} type="password" required />
        <Field
          label="Confirm password"
          value={confirm}
          onChange={setConfirm}
          type="password"
          required
        />
        {mismatch ? (
          <p className="text-sm text-danger" role="alert">
            Passwords do not match.
          </p>
        ) : null}
      </form>
    </Modal>
  );
}
