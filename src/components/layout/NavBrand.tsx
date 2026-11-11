import Image from "next/image";
import Link from "next/link";
import { site } from "@/data/site";

export const PLAN_MSG = "Hi Reyaan Trip Holiday, I'd like to plan a trip";

export function isActive(pathname: string, href: string) {
  if (href === "/") return pathname === "/";
  return pathname === href || pathname.startsWith(`${href}/`);
}

/**
 * Logo + wordmark. The logo JPEG (navy/blue on a light-grey ground) sits directly on the white header
 * with only a subtle radius. Wordmark in Playfair, subline as a small label on sm+.
 * The subline is hidden between lg and xl, where the desktop nav needs the room.
 * Shared by the navbar and the lazily loaded mobile menu.
 */
export function Brand({ onClick }: { onClick?: () => void }) {
  return (
    <Link href="/" onClick={onClick} className="group flex min-h-11 min-w-0 items-center gap-2.5 rounded-lg sm:gap-3">
      <Image
        src={site.logo}
        alt=""
        width={627}
        height={497}
        loading="eager"
        sizes="52px"
        className="h-9 w-auto shrink-0 rounded-md object-contain lg:h-10"
      />
      <span className="flex min-w-0 flex-col justify-center">
        <span className="truncate font-display text-base font-semibold leading-tight text-ink transition-colors duration-300 group-hover:text-primary sm:text-lg">
          {site.name}
        </span>
        <span className="label mt-0.5 hidden truncate text-[10px] leading-none tracking-[0.14em] sm:block lg:hidden xl:block">
          Srinagar · Kashmir · Ladakh
        </span>
      </span>
    </Link>
  );
}
