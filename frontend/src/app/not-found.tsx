import Link from "next/link";

export default function NotFound(): JSX.Element {
  return (
    <div className="mx-auto max-w-md space-y-4 py-16 text-center">
      <h1 className="text-lg font-semibold">Page not found</h1>
      <p className="text-sm text-muted-foreground">The page you are looking for does not exist or was moved.</p>
      <Link href="/" className="text-sm font-medium text-primary underline">
        Go to home
      </Link>
    </div>
  );
}