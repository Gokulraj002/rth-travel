import type { Destination, Region } from "@/types";

/**
 * Destinations, ordered as the globe journey visits them on the home page.
 * Photos: Unsplash (free licence) — stored locally under /public/images/destinations.
 */
export const destinations: Destination[] = [
  {
    slug: "srinagar",
    name: "Srinagar",
    displayName: "SRINAGAR",
    region: "kashmir",
    tagline: "Houseboats, shikaras & Mughal gardens",
    description:
      "The summer capital of Jammu & Kashmir, wrapped around the mirror-still waters of Dal and Nigeen lakes. Wake up on a carved cedar houseboat, drift through floating vegetable markets at dawn by shikara and walk the terraced Mughal gardens of Nishat and Shalimar with the Zabarwan range behind you.",
    geo: { lat: 34.0837, lng: 74.7973 },
    altitudeM: 1585,
    bestTime: "March – October · Tulip season in April",
    distanceNote: "Our home base · 15 min from Srinagar airport (SXR)",
    highlights: ["Dal Lake", "Houseboat stay", "Nishat & Shalimar Bagh", "Tulip Garden", "Old City & Jamia Masjid"],
    thingsToDo: [
      "Sunrise shikara ride to the floating market",
      "Overnight on a heritage houseboat",
      "Walk the Mughal gardens — Nishat, Shalimar, Chashme Shahi",
      "Shop for Pashmina, papier-mâché and saffron",
      "Taste a traditional Wazwan feast",
    ],
    image: "/images/destinations/srinagar.jpg",
    gallery: [
      "/images/destinations/srinagar-2.jpg",
      "/images/destinations/srinagar-3.jpg",
      "/images/destinations/srinagar-4.jpg",
      "/images/destinations/srinagar-5.jpg",
    ],
    credit: "Unsplash",
  },
  {
    slug: "gulmarg",
    name: "Gulmarg",
    displayName: "GULMARG",
    region: "kashmir",
    tagline: "Meadow of flowers, kingdom of snow",
    description:
      "A cup-shaped meadow at the foot of Apharwat peak — wildflowers in summer, waist-deep powder in winter. Ride one of the highest cable cars in the world, the Gulmarg Gondola, then sledge, ski or simply sit with a kahwa and watch the snow peaks turn gold.",
    geo: { lat: 34.0484, lng: 74.3805 },
    altitudeM: 2650,
    bestTime: "December – March for snow · May – September for meadows",
    distanceNote: "≈ 50 km · 1.5 – 2 hrs from Srinagar",
    highlights: ["Gulmarg Gondola", "Apharwat Peak", "Skiing & sledging", "St. Mary's Church", "Strawberry valley"],
    thingsToDo: [
      "Gondola phase 1 & 2 up to Apharwat",
      "Ski lessons with certified instructors",
      "Sledge and snow-bike rides",
      "Pony ride to Khilanmarg",
      "Golf at one of the highest greens in the world",
    ],
    image: "/images/destinations/gulmarg.jpg",
    gallery: [
      "/images/destinations/gulmarg-2.jpg",
      "/images/destinations/gulmarg-3.jpg",
      "/images/destinations/gulmarg-4.jpg",
    ],
    credit: "Unsplash",
  },
  {
    slug: "pahalgam",
    name: "Pahalgam",
    displayName: "PAHALGAM",
    region: "kashmir",
    tagline: "Valley of shepherds on the Lidder river",
    description:
      "Pine forests, rushing glacial water and wide green valleys made famous by Bollywood. Pahalgam is the gateway to Betaab, Aru and Chandanwari, and the base for the Amarnath Yatra — the perfect place to slow down for a couple of nights.",
    geo: { lat: 34.0161, lng: 75.315 },
    altitudeM: 2740,
    bestTime: "April – October",
    distanceNote: "≈ 95 km · 2.5 – 3 hrs from Srinagar",
    highlights: ["Betaab Valley", "Aru Valley", "Chandanwari", "Baisaran meadows", "Lidder river"],
    thingsToDo: [
      "Pony ride to Baisaran — 'Mini Switzerland'",
      "Picnic in Betaab Valley",
      "Trout fishing on the Lidder",
      "River rafting (seasonal)",
      "Riverside camping and bonfire",
    ],
    image: "/images/destinations/pahalgam.jpg",
    gallery: [
      "/images/destinations/pahalgam-2.jpg",
      "/images/destinations/pahalgam-3.jpg",
      "/images/destinations/pahalgam-4.jpg",
    ],
    credit: "Unsplash",
  },
  {
    slug: "sonamarg",
    name: "Sonamarg",
    displayName: "SONAMARG",
    region: "kashmir",
    tagline: "Meadow of gold below the glaciers",
    description:
      "The last great meadow before the Zojila pass to Ladakh. Sonamarg sits beneath the Thajiwas glacier, with the Sindh river cutting through the valley — snow you can touch even in June.",
    geo: { lat: 34.3036, lng: 75.293 },
    altitudeM: 2800,
    bestTime: "April – October",
    distanceNote: "≈ 80 km · 2.5 – 3 hrs from Srinagar",
    highlights: ["Thajiwas Glacier", "Zero Point, Zojila", "Sindh river", "Vishansar trek base"],
    thingsToDo: [
      "Pony or sledge ride to Thajiwas glacier",
      "Snow point at Zero Point (seasonal)",
      "River rafting on the Sindh",
      "Day hikes towards the Great Lakes",
    ],
    image: "/images/destinations/sonamarg.jpg",
    gallery: [
      "/images/destinations/sonamarg-2.jpg",
      "/images/destinations/sonamarg-3.jpg",
      "/images/destinations/sonamarg-4.jpg",
    ],
    credit: "Unsplash",
  },
  {
    slug: "ladakh",
    name: "Ladakh",
    displayName: "LADAKH",
    region: "ladakh",
    tagline: "Land of high passes",
    description:
      "A high-altitude desert of moonscapes, monasteries and impossibly blue lakes. From Leh, cross Khardung La to the sand dunes and double-humped camels of Nubra, then drive to Pangong Tso, the 134 km lake that changes colour with the sky.",
    geo: { lat: 34.1526, lng: 77.5771 },
    altitudeM: 3500,
    bestTime: "May – September",
    distanceNote: "≈ 420 km from Srinagar by road · or 1 hr flight to Leh",
    highlights: ["Pangong Tso", "Nubra Valley", "Khardung La", "Thiksey & Hemis monasteries", "Indus–Zanskar Sangam"],
    thingsToDo: [
      "Overnight camp beside Pangong lake",
      "Bactrian camel ride at Hunder dunes",
      "Cross Khardung La, one of the highest motorable passes",
      "Morning prayers at Thiksey monastery",
      "Rafting at the Indus–Zanskar confluence",
    ],
    image: "/images/destinations/ladakh.jpg",
    gallery: [
      "/images/destinations/ladakh-2.jpg",
      "/images/destinations/ladakh-3.jpg",
      "/images/destinations/ladakh-4.jpg",
      "/images/destinations/ladakh-5.jpg",
      "/images/destinations/ladakh-6.jpg",
      "/images/destinations/ladakh-7.jpg",
    ],
    credit: "Unsplash",
  },
  {
    slug: "katra",
    name: "Katra",
    displayName: "KATRA",
    region: "jammu",
    tagline: "Gateway to Mata Vaishno Devi",
    description:
      "The base town for the sacred Mata Vaishno Devi shrine in the Trikuta hills. Pilgrims walk the 12 km path to the Bhawan — or take a pony, palki or helicopter — and the whole mountain glows with lights through the night.",
    geo: { lat: 32.9916, lng: 74.9319 },
    altitudeM: 754,
    bestTime: "March – October · Navratri is the most auspicious",
    distanceNote: "≈ 50 km · 1.5 hrs from Jammu",
    highlights: ["Mata Vaishno Devi Bhawan", "Bhairon Temple", "Ardhkuwari", "Helicopter darshan"],
    thingsToDo: [
      "Trek to the Bhawan via the new Tarakote route",
      "Helicopter booking assistance from Katra to Sanjichhat",
      "Ropeway to Bhairon Baba temple",
      "Evening aarti darshan",
    ],
    image: "/images/destinations/katra.jpg",
    gallery: [
      "/images/destinations/katra-2.jpg",
      "/images/destinations/katra-3.jpg",
      "/images/destinations/katra-4.jpg",
    ],
    credit: "Unsplash",
  },
  {
    slug: "jammu",
    name: "Jammu",
    displayName: "JAMMU",
    region: "jammu",
    tagline: "City of temples on the Tawi",
    description:
      "The winter capital, perched on the banks of the Tawi river. Explore the Raghunath temple complex, Bahu Fort above the river and the Dogra palaces of Mubarak Mandi — then escape to the pine-scented heights of Patnitop.",
    geo: { lat: 32.7266, lng: 74.857 },
    altitudeM: 327,
    bestTime: "October – March",
    distanceNote: "Railhead & airport (IXJ) · ≈ 260 km to Srinagar",
    highlights: ["Raghunath Temple", "Bahu Fort", "Mubarak Mandi Palace", "Amar Mahal", "Patnitop day trip"],
    thingsToDo: [
      "Darshan at Raghunath and Ranbireshwar temples",
      "Sunset at Bahu Fort and Bagh-e-Bahu",
      "Day trip to Patnitop and Sanasar",
      "Try Kalari cheese and Rajma-Chawal",
    ],
    image: "/images/destinations/jammu.jpg",
    gallery: ["/images/destinations/jammu-2.jpg", "/images/destinations/jammu-3.jpg"],
    credit: "Unsplash",
  },
];

export const regionLabels: Record<Region, string> = {
  kashmir: "Kashmir Valley",
  ladakh: "Ladakh",
  jammu: "Jammu Region",
};

export function getDestination(slug: string) {
  return destinations.find((d) => d.slug === slug);
}
