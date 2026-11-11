/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  // Produce a self-contained server bundle (.next/standalone) so the Docker
  // runtime image can run `node server.js` without the full node_modules tree.
  output: "standalone",
  // Allow serving remotely hosted tenant logos/favicons without build-time
  // host allow-listing failures. Branding assets are tenant-controlled URLs
  // returned by the backend, so we render them via <img>/<link> rather than
  // next/image to avoid requiring a static remotePatterns allow-list.
};

export default nextConfig;
