import type { Faq, Testimonial, Vehicle } from "@/types";

/**
 * Marketing copy shared across pages.
 * Keep claims factual — no invented years in business, traveller counts, awards or ratings.
 */

export interface WhyUsItem {
  title: string;
  description: string;
  /** lucide-react icon name, e.g. "MapPin" */
  icon: "MapPin" | "Car" | "ShieldCheck" | "Headphones" | "BedDouble" | "Wallet";
}

export const whyUs: WhyUsItem[] = [
  {
    title: "Local team in Srinagar",
    description:
      "We are based at Nowgam, Srinagar — not a call centre in another city. Our team knows these valleys, roads and seasons first-hand and plans every trip from right here.",
    icon: "MapPin",
  },
  {
    title: "Our own vehicles & local drivers",
    description:
      "From sedans to our branded Reyaan Trip Holiday Tempo Traveller, you travel in our own vehicles with experienced local drivers who know the mountain roads.",
    icon: "Car",
  },
  {
    title: "Handpicked houseboats & hotels",
    description:
      "We choose houseboats on Dal Lake and hotels in Gulmarg, Pahalgam, Sonamarg and Leh for their location, cleanliness and hospitality — and match them to your budget.",
    icon: "BedDouble",
  },
  {
    title: "24×7 on-trip support on WhatsApp",
    description:
      "One message reaches our team at any hour while you travel — for a change of plan, a late flight or a question about the weather.",
    icon: "Headphones",
  },
  {
    title: "Transparent pricing",
    description:
      "Every quote lists exactly what is included and what is not — gondola tickets, pony rides and union cabs are spelled out upfront, so there are no hidden costs.",
    icon: "Wallet",
  },
  {
    title: "Safe routes & permits handled",
    description:
      "We plan around road and weather updates, build in acclimatisation for Ladakh, and arrange the Inner Line / protected-area permits needed for Nubra and Pangong.",
    icon: "ShieldCheck",
  },
];

/** Vehicle options for transfers and tours (exact model depends on availability). */
export const fleet: Vehicle[] = [
  {
    name: "Sedan",
    seats: "4 seats",
    description:
      "Swift Dzire or similar — a comfortable, economical air-conditioned car for couples and small families.",
    idealFor: "Couples & families of up to 4 on Kashmir valley tours",
    features: [
      "Air-conditioned",
      "Boot space for 2–3 suitcases",
      "Local driver who knows the routes",
      "Ideal for airport transfers & city sightseeing",
    ],
  },
  {
    name: "SUV",
    seats: "6–7 seats",
    description:
      "Toyota Innova Crysta or similar — extra legroom and luggage space for longer drives across Kashmir and Jammu.",
    idealFor: "Families and small groups wanting more comfort",
    features: [
      "Spacious captain / bench seating",
      "Generous luggage space",
      "Smooth ride on long highway days",
      "Air-conditioned",
    ],
  },
  {
    name: "Tempo Traveller",
    seats: "12–17 seats",
    description:
      "Our branded Reyaan Trip Holiday traveller — the easy way to keep a large family, a group of friends or a pilgrimage party together.",
    idealFor: "Groups, extended families & yatra parties",
    features: [
      "Push-back seats",
      "Roof carrier and rear luggage space",
      "One vehicle for the whole group",
      "Experienced driver for long routes",
    ],
  },
  {
    name: "4×4 for Ladakh & Snow",
    seats: "6–7 seats",
    description:
      "Mahindra Scorpio or similar — high ground clearance for Ladakh's high passes and Gulmarg's snowy roads.",
    idealFor: "Ladakh circuits, Srinagar–Leh road trips & winter snow trips",
    features: [
      "High ground clearance for mountain roads",
      "Snow chains in winter when required",
      "Drivers experienced on Khardung La, Chang La & Zojila",
      "Space for luggage and winter gear",
    ],
  },
];

/*
 * ⚠️ SAMPLE testimonials — replace with real guest reviews before going live.
 * Names, cities and quotes below are placeholders for layout only.
 */
/** SAMPLE testimonials — replace with real guest reviews before going live. */
export const testimonials: Testimonial[] = [
  {
    name: "Priya S.",
    from: "Pune",
    trip: "Kashmir Honeymoon",
    quote:
      "The houseboat night and the candle-light shikara were the highlight of our honeymoon. Our driver was patient, knew every stop and never made us feel rushed.",
    rating: 5,
  },
  {
    name: "Rahul M.",
    from: "Bengaluru",
    trip: "Ladakh High Passes",
    quote:
      "The rest day in Leh made a real difference — we felt fine crossing Khardung La. Permits were sorted before we landed and the team checked in with us on WhatsApp every day.",
    rating: 5,
  },
  {
    name: "Anita K.",
    from: "Ahmedabad",
    trip: "Jammu, Katra & Kashmir",
    quote:
      "Travelling with my parents, I wanted something comfortable. Katra was well organised, and the drive to Srinagar via Patnitop broke the journey nicely.",
    rating: 5,
  },
  {
    name: "Vikram T.",
    from: "Delhi",
    trip: "Kashmir Winter Snow",
    quote:
      "Gulmarg in January was magical. The gondola had a weather delay one morning, but the driver adjusted our plan and we still got our ski lesson in.",
    rating: 4,
  },
];

export const faqs: Faq[] = [
  {
    q: "What is the best time to visit Kashmir?",
    a: "Kashmir is a year-round destination. March to May brings tulips, blossoms and pleasant days; June to August is ideal for green meadows, Sonamarg and the Srinagar–Leh road; September to November has clear skies and golden chinar trees; and December to February is the time for snow and skiing in Gulmarg. Ladakh is best visited from about May to September.",
  },
  {
    q: "Is Kashmir safe for tourists?",
    a: "Lakhs of domestic and international tourists visit Kashmir every year, and the main tourist areas — Srinagar, Gulmarg, Pahalgam and Sonamarg — have a strong tourism and security presence. As with any mountain destination, conditions can change, so we keep an eye on local advisories, road and weather updates, and adjust your plan when needed. Our local team stays in touch with you throughout the trip.",
  },
  {
    q: "What is included in your packages?",
    a: "Most packages include accommodation on twin sharing, daily breakfast and dinner, a private cab with driver for the whole trip, airport or railway transfers, sightseeing as per the itinerary, and driver allowances, tolls and parking. Each package page lists its exact inclusions and exclusions — things like flights, Gulmarg Gondola tickets, pony rides and local union cabs are usually extra.",
  },
  {
    q: "How do I book a trip?",
    a: "Send us a message on WhatsApp or call us with your travel dates, number of travellers and the kind of trip you have in mind. We will share a detailed quote and itinerary, and once you are happy with it, we share the booking confirmation details, payment and cancellation terms directly with you.",
  },
  {
    q: "How do I deal with altitude sickness in Ladakh?",
    a: "Leh sits at about 3,500 m, so our Ladakh itineraries begin with a full rest day after you fly in. Drink plenty of water, avoid alcohol and exertion for the first 48 hours, and ascend gradually — the Srinagar–Leh road trip gains height more slowly. If you have a heart, lung or blood-pressure condition, consult your doctor before travelling, and tell us immediately if you feel unwell on the trip.",
  },
  {
    q: "Do I need a permit for Nubra Valley and Pangong Lake?",
    a: "Yes. Protected areas such as Nubra Valley and Pangong Tso require a permit issued by the Leh administration (commonly called the Inner Line Permit; foreign nationals need a Protected Area Permit). We arrange it for you on our Ladakh packages — just share a copy of your government photo ID in advance. Government fees and rules can change and are charged as applicable.",
  },
  {
    q: "Why do I need local union taxis at Gulmarg, Pahalgam and Sonamarg?",
    a: "At Pahalgam, Sonamarg and parts of Gulmarg, local taxi unions operate the vehicles to certain sightseeing points — for example Aru, Betaab Valley and Chandanwari from Pahalgam, or Zero Point beyond Sonamarg. Outside vehicles, including ours, are not permitted on these stretches, so union cabs and pony rides are paid locally at the fixed union rates and are listed as exclusions in our packages.",
  },
  {
    q: "Can you customise an itinerary for us?",
    a: "Absolutely. Every package can be tailored — add or remove destinations, change the number of nights, upgrade hotels or houseboats, or switch vehicles. Tell us your dates, group size and budget and we will plan a trip around you.",
  },
];
