// Tailwind CSS v4 via PostCSS — works with both webpack (`next build --webpack`, required on Hostinger
// for Next 16.3.3+) and Turbopack (`next dev`), unlike the Turbopack-only @tailwindcss/turbopack loader.
const config = {
  plugins: {
    "@tailwindcss/postcss": {},
  },
};

export default config;
