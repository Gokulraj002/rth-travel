import type { TourPackage } from "@/types";

// Category display names live in ./labels.ts (shared with client components).

/**
 * Tour packages.
 *
 * ⚠️ PRICES ARE PLACEHOLDERS — `priceFrom` is an indicative per-person INR starting
 * price (twin sharing) written for layout purposes only. Confirm every value with the
 * owner before launch. Itineraries use real routes and typical drive times; road
 * conditions, permits and seasonal closures always decide the final plan.
 */
export const packages: TourPackage[] = [
  /* ------------------------------------------------------------------ */
  {
    slug: "kashmir-paradise",
    title: "Kashmir Paradise",
    subtitle: "Srinagar, Sonamarg, Gulmarg & Pahalgam in one easy loop",
    destinations: ["srinagar", "sonamarg", "gulmarg", "pahalgam"],
    region: "kashmir",
    categories: ["family", "honeymoon"],
    nights: 5,
    days: 6,
    priceFrom: 15999,
    image: "/images/destinations/pahalgam-2.jpg",
    featured: true,
    highlights: [
      "Shikara ride on Dal Lake at sunset",
      "Thajiwas Glacier views at Sonamarg",
      "Gulmarg Gondola to Kongdoori (tickets extra)",
      "Betaab Valley, Aru Valley & the Lidder river at Pahalgam",
      "Saffron fields of Pampore and the Awantipora temple ruins",
      "Mughal Gardens — Nishat, Shalimar & Chashme Shahi",
    ],
    inclusions: [
      "5 nights' accommodation on twin sharing (houseboat / hotel as per itinerary)",
      "Daily breakfast & dinner",
      "Private cab with driver for the entire trip",
      "Srinagar airport pick-up and drop",
      "Sightseeing as per itinerary",
      "1-hour shikara ride on Dal Lake",
      "Driver allowances, fuel, tolls & parking",
    ],
    exclusions: [
      "Airfare / train tickets",
      "Gulmarg Gondola tickets",
      "Pony rides and local union cabs at Sonamarg, Gulmarg & Pahalgam",
      "Garden and monument entry fees",
      "Lunch, personal expenses, tips and anything not listed in inclusions",
      "Travel insurance",
      "GST, if applicable",
    ],
    itinerary: [
      {
        day: 1,
        title: "Arrive Srinagar · Dal Lake shikara",
        description:
          "Pick-up from Srinagar airport and transfer to your houseboat or hotel. In the evening, glide across Dal Lake on a shikara past floating gardens and the lake market. Dinner and overnight in Srinagar.",
        stay: "Srinagar",
      },
      {
        day: 2,
        title: "Day trip to Sonamarg",
        description:
          "Drive about 80 km along the Sindh river to Sonamarg, the 'Meadow of Gold'. Hire a pony or walk towards Thajiwas Glacier, then return to Srinagar by evening.",
        stay: "Srinagar",
      },
      {
        day: 3,
        title: "Srinagar → Gulmarg",
        description:
          "A 1.5–2 hour drive through Tangmarg brings you to Gulmarg. Ride the Gulmarg Gondola to Kongdoori (Phase 1) or on to Apharwat (Phase 2, weather permitting), and stroll past St. Mary's Church and the golf course.",
        stay: "Gulmarg",
      },
      {
        day: 4,
        title: "Gulmarg → Pahalgam",
        description:
          "Drive to Pahalgam (around 4–5 hours), stopping at the saffron fields of Pampore and the 9th-century Awantipora temple ruins. Evening walk along the Lidder river.",
        stay: "Pahalgam",
      },
      {
        day: 5,
        title: "Pahalgam valleys → Srinagar",
        description:
          "Morning visit to Betaab Valley, Aru Valley or Chandanwari by local union cab. Drive back to Srinagar and visit the Mughal Gardens — Nishat, Shalimar and Chashme Shahi — if time allows.",
        stay: "Srinagar",
      },
      {
        day: 6,
        title: "Departure",
        description:
          "After breakfast, a little time for shopping for pashmina, dry fruits or saffron before your drop at Srinagar airport.",
      },
    ],
  },

  /* ------------------------------------------------------------------ */
  {
    slug: "kashmir-honeymoon",
    title: "Kashmir Honeymoon",
    subtitle: "A houseboat night, a candle-light shikara and slow days in the mountains",
    destinations: ["srinagar", "gulmarg", "pahalgam"],
    region: "kashmir",
    categories: ["honeymoon"],
    nights: 6,
    days: 7,
    priceFrom: 21999,
    image: "/images/destinations/srinagar-3.jpg",
    featured: true,
    highlights: [
      "One night on a traditional Dal Lake houseboat",
      "Candle-light shikara ride on your last evening",
      "Overnight stays in Gulmarg and Pahalgam — not rushed day trips",
      "Gulmarg Gondola to Kongdoori & Apharwat (tickets extra)",
      "Aru Valley, Betaab Valley & Chandanwari",
      "Mughal Gardens and Pari Mahal above Dal Lake",
    ],
    inclusions: [
      "6 nights' accommodation on twin sharing (1 night houseboat, 5 nights hotel)",
      "Daily breakfast & dinner",
      "Private cab with driver for the entire trip",
      "Srinagar airport pick-up and drop",
      "Sightseeing as per itinerary",
      "Sunset shikara ride and a candle-light shikara ride on Dal Lake",
      "Driver allowances, fuel, tolls & parking",
    ],
    exclusions: [
      "Airfare / train tickets",
      "Gulmarg Gondola tickets",
      "Pony rides and local union cabs at Gulmarg & Pahalgam",
      "Garden and monument entry fees",
      "Lunch, personal expenses and tips",
      "Travel insurance",
      "GST, if applicable",
    ],
    itinerary: [
      {
        day: 1,
        title: "Arrive Srinagar · Houseboat night",
        description:
          "Airport pick-up and transfer to your houseboat on Dal Lake. Spend the evening on a sunset shikara ride as the lake lights up. Dinner and overnight on the houseboat.",
        stay: "Houseboat, Dal Lake",
      },
      {
        day: 2,
        title: "Srinagar sightseeing",
        description:
          "Visit Nishat Bagh, Shalimar Bagh and Chashme Shahi, then Pari Mahal for sweeping views over Dal Lake. Check in to your hotel in Srinagar for the night.",
        stay: "Srinagar",
      },
      {
        day: 3,
        title: "Srinagar → Gulmarg",
        description:
          "Drive to Gulmarg (around 50 km). Take the Gulmarg Gondola to Kongdoori, and on to Apharwat if the weather allows. Quiet evening in the meadow town.",
        stay: "Gulmarg",
      },
      {
        day: 4,
        title: "Gulmarg → Pahalgam",
        description:
          "A scenic drive to Pahalgam via the saffron town of Pampore and the Awantipora ruins. Settle in by the Lidder river and enjoy the evening at leisure.",
        stay: "Pahalgam",
      },
      {
        day: 5,
        title: "Pahalgam valleys",
        description:
          "Explore Aru Valley, Betaab Valley and Chandanwari by local union cab, or simply walk the riverside trails. A slow day to enjoy the pine forests together.",
        stay: "Pahalgam",
      },
      {
        day: 6,
        title: "Pahalgam → Srinagar · Candle-light shikara",
        description:
          "Return to Srinagar after breakfast with time for shopping in the old city. As evening falls, a candle-light shikara ride on Dal Lake rounds off the trip.",
        stay: "Srinagar",
      },
      {
        day: 7,
        title: "Departure",
        description: "Breakfast and drop at Srinagar airport with memories to last a lifetime.",
      },
    ],
  },

  /* ------------------------------------------------------------------ */
  {
    slug: "kashmir-winter-snow",
    title: "Kashmir Winter Snow",
    subtitle: "Gulmarg in deep snow — gondola, skiing and snow play (Dec – Feb)",
    destinations: ["srinagar", "gulmarg"],
    region: "kashmir",
    categories: ["snow", "adventure"],
    nights: 4,
    days: 5,
    priceFrom: 13999,
    image: "/images/destinations/gulmarg-3.jpg",
    highlights: [
      "Two nights in snow-covered Gulmarg",
      "Gulmarg Gondola up to Apharwat, weather permitting (tickets extra)",
      "Beginner ski lessons or sledging on the slopes",
      "Kahwa by the fire and Kashmiri wazwan in Srinagar",
      "Winter views of Dal Lake and the Zabarwan range",
    ],
    inclusions: [
      "4 nights' accommodation on twin sharing with room heating",
      "Daily breakfast & dinner",
      "Private cab with driver (snow chains fitted when required)",
      "Srinagar airport pick-up and drop",
      "Sightseeing as per itinerary",
      "Driver allowances, fuel, tolls & parking",
    ],
    exclusions: [
      "Airfare / train tickets",
      "Gulmarg Gondola tickets",
      "Ski equipment hire, ski instructors and sledges",
      "Snow boots / jackets on hire",
      "Local union cabs at Gulmarg where applicable",
      "Personal expenses and travel insurance",
      "GST, if applicable",
    ],
    itinerary: [
      {
        day: 1,
        title: "Arrive Srinagar",
        description:
          "Pick-up from Srinagar airport and check in. If the weather is kind, take a short shikara ride on Dal Lake; otherwise warm up with kahwa. Overnight in Srinagar.",
        stay: "Srinagar",
      },
      {
        day: 2,
        title: "Srinagar → Gulmarg",
        description:
          "Drive to Gulmarg through snowy Tangmarg (snow chains may be needed on the final stretch). Afternoon of snow play, sledging or a first look at the ski slopes.",
        stay: "Gulmarg",
      },
      {
        day: 3,
        title: "Gondola & ski day",
        description:
          "Ride the Gulmarg Gondola to Kongdoori and on to Apharwat when Phase 2 is operating. Book a beginner ski lesson with a certified local instructor or simply enjoy the views.",
        stay: "Gulmarg",
      },
      {
        day: 4,
        title: "Gulmarg → Srinagar",
        description:
          "Return to Srinagar. Visit Shankaracharya Temple or Pari Mahal for winter views, and the old-city markets for pashmina and walnut-wood crafts.",
        stay: "Srinagar",
      },
      {
        day: 5,
        title: "Departure",
        description:
          "Breakfast and drop at Srinagar airport. Winter flights can be affected by weather, so we keep in touch until you are checked in.",
      },
    ],
  },

  /* ------------------------------------------------------------------ */
  {
    slug: "ladakh-high-passes",
    title: "Ladakh High Passes",
    subtitle: "Leh, Nubra via Khardung La and Pangong Lake — with a proper acclimatisation day",
    destinations: ["ladakh"],
    region: "ladakh",
    categories: ["adventure", "group"],
    nights: 6,
    days: 7,
    priceFrom: 24999,
    image: "/images/destinations/ladakh-3.jpg",
    featured: true,
    highlights: [
      "Full rest day on arrival to acclimatise at 3,500 m",
      "Khardung La (≈ 5,350 m) on the way to Nubra Valley",
      "Hunder sand dunes and Diskit Monastery",
      "Pangong Tso via the Shyok river route",
      "Chang La pass, Thiksey Monastery and Shey Palace",
      "Shanti Stupa, Leh Palace and Magnetic Hill",
    ],
    inclusions: [
      "6 nights' accommodation on twin sharing (hotels in Leh, camps / guesthouses at Nubra & Pangong)",
      "Daily breakfast & dinner",
      "Private SUV with an experienced Ladakh driver",
      "Leh airport pick-up and drop",
      "Inner Line / protected area permits arranged for Nubra & Pangong (government fees as applicable)",
      "Sightseeing as per itinerary",
      "Driver allowances, fuel, tolls & parking",
    ],
    exclusions: [
      "Airfare to / from Leh",
      "Camel safari at Hunder and monastery entry fees",
      "Oxygen cylinders and medical expenses",
      "Lunch, personal expenses and tips",
      "Travel insurance (strongly recommended for high altitude)",
      "GST, if applicable",
    ],
    itinerary: [
      {
        day: 1,
        title: "Arrive Leh · Rest & acclimatise",
        description:
          "Pick-up from Leh airport (IXL) and transfer to your hotel. Rest completely today — drink plenty of water, avoid exertion and let your body adjust to 3,500 m. Short evening stroll in Leh market only if you feel well.",
        stay: "Leh",
      },
      {
        day: 2,
        title: "Leh local sightseeing",
        description:
          "An easy day: Shanti Stupa, Leh Palace, the Hall of Fame museum, Gurudwara Pathar Sahib, Magnetic Hill and the Indus–Zanskar confluence at Sangam.",
        stay: "Leh",
      },
      {
        day: 3,
        title: "Leh → Khardung La → Nubra Valley",
        description:
          "Drive about 120 km over Khardung La (≈ 5,350 m) into Nubra Valley. Visit Diskit Monastery and its giant Maitreya Buddha, then the Hunder sand dunes where double-humped Bactrian camels graze.",
        stay: "Nubra Valley",
      },
      {
        day: 4,
        title: "Nubra → Pangong Lake via Shyok",
        description:
          "Follow the Shyok river on the direct route to Pangong Tso (around 6–7 hours, road conditions permitting). Evening by the lake as its colours shift from turquoise to deep blue.",
        stay: "Pangong",
      },
      {
        day: 5,
        title: "Pangong → Chang La → Leh",
        description:
          "Sunrise at Pangong, then drive back to Leh over Chang La. Stop at Thiksey Monastery and Shey Palace on the way.",
        stay: "Leh",
      },
      {
        day: 6,
        title: "Hemis Monastery · Leisure",
        description:
          "Visit Hemis Monastery, the largest in Ladakh, then spend the afternoon at leisure in Leh for cafés and shopping.",
        stay: "Leh",
      },
      {
        day: 7,
        title: "Departure",
        description: "Early-morning drop at Leh airport for your onward flight.",
      },
    ],
  },

  /* ------------------------------------------------------------------ */
  {
    slug: "srinagar-leh-road-trip",
    title: "Srinagar to Leh Road Trip",
    subtitle: "Over Zojila to Kargil, Leh, Nubra and Pangong — the classic Himalayan drive",
    destinations: ["srinagar", "sonamarg", "ladakh"],
    region: "ladakh",
    categories: ["adventure"],
    nights: 9,
    days: 10,
    priceFrom: 34999,
    image: "/images/destinations/ladakh-5.jpg",
    highlights: [
      "Zojila pass (≈ 3,530 m) from Sonamarg into Ladakh",
      "Kargil War Memorial at Drass",
      "Mulbekh Maitreya, Namika La, Fotu La and Lamayuru 'moonland'",
      "Khardung La to Nubra Valley and the Hunder dunes",
      "Pangong Tso via the Shyok route, return over Chang La",
      "Gradual gain in altitude — kinder on the body than flying in",
    ],
    inclusions: [
      "9 nights' accommodation on twin sharing (hotels, camps / guesthouses at Nubra & Pangong)",
      "Daily breakfast & dinner",
      "Private SUV with an experienced mountain driver for the full route",
      "Srinagar airport pick-up and Leh airport drop",
      "Inner Line / protected area permits arranged for Nubra & Pangong (government fees as applicable)",
      "Shikara ride on Dal Lake",
      "Driver allowances, fuel, tolls & parking",
    ],
    exclusions: [
      "Airfare (into Srinagar and out of Leh)",
      "Pony rides and local union cabs at Sonamarg",
      "Camel safari, monastery and museum entry fees",
      "Oxygen cylinders and medical expenses",
      "Lunch, personal expenses and tips",
      "Travel insurance",
      "GST, if applicable",
    ],
    itinerary: [
      {
        day: 1,
        title: "Arrive Srinagar",
        description:
          "Airport pick-up and check in. Evening shikara ride on Dal Lake and an early night before the road journey.",
        stay: "Srinagar",
      },
      {
        day: 2,
        title: "Srinagar → Sonamarg",
        description:
          "A short 80 km drive along the Sindh river to Sonamarg (≈ 2,800 m) — a gentle first step up in altitude. Afternoon walk or pony ride towards Thajiwas Glacier.",
        stay: "Sonamarg",
      },
      {
        day: 3,
        title: "Sonamarg → Zojila → Drass → Kargil",
        description:
          "Cross Zojila pass into Ladakh and stop at the Kargil War Memorial in Drass, one of the coldest inhabited places in India. Continue to Kargil on the Suru river (about 125 km in all).",
        stay: "Kargil",
      },
      {
        day: 4,
        title: "Kargil → Lamayuru → Leh",
        description:
          "A long, spectacular day of around 220 km: the rock-cut Maitreya at Mulbekh, Namika La and Fotu La, Lamayuru Monastery and its 'moonland', then along the Indus past Magnetic Hill into Leh.",
        stay: "Leh",
      },
      {
        day: 5,
        title: "Leh · Rest & easy sightseeing",
        description:
          "A slow day to acclimatise. Late afternoon visit to Shanti Stupa and Leh Palace, with a walk through Leh market.",
        stay: "Leh",
      },
      {
        day: 6,
        title: "Monasteries of the Indus valley",
        description:
          "Visit Hemis Monastery, Thiksey Monastery and Shey Palace, returning to Leh by evening. Keep sipping water and eat light.",
        stay: "Leh",
      },
      {
        day: 7,
        title: "Leh → Khardung La → Nubra Valley",
        description:
          "Drive over Khardung La (≈ 5,350 m) into Nubra. Visit Diskit Monastery and the Hunder sand dunes with their Bactrian camels.",
        stay: "Nubra Valley",
      },
      {
        day: 8,
        title: "Nubra → Pangong Lake",
        description:
          "Follow the Shyok river to Pangong Tso (around 6–7 hours, road conditions permitting). Evening on the shore of the famous high-altitude lake.",
        stay: "Pangong",
      },
      {
        day: 9,
        title: "Pangong → Chang La → Leh",
        description:
          "Return to Leh over Chang La after a sunrise at the lake. Free evening for last-minute shopping and a farewell dinner.",
        stay: "Leh",
      },
      {
        day: 10,
        title: "Departure from Leh",
        description:
          "Drop at Leh airport for your flight home. (The Srinagar–Leh road is usually open from about May to October, depending on snow clearance on Zojila.)",
      },
    ],
  },

  /* ------------------------------------------------------------------ */
  {
    slug: "vaishno-devi-yatra",
    title: "Vaishno Devi Yatra",
    subtitle: "Jammu – Katra – Bhawan – Bhairon, with comfortable stays in Katra",
    destinations: ["jammu", "katra"],
    region: "jammu",
    categories: ["pilgrimage", "family"],
    nights: 2,
    days: 3,
    priceFrom: 6999,
    image: "/images/destinations/katra-3.jpg",
    featured: true,
    highlights: [
      "Darshan at the holy cave shrine of Mata Vaishno Devi",
      "Bhairon temple visit to complete the yatra",
      "Help with yatra registration (RFID card) at Katra",
      "Walk, pony, palki, battery car or helicopter — your choice",
      "Raghunath Temple in Jammu on the way back",
    ],
    inclusions: [
      "2 nights' accommodation in Katra on twin sharing",
      "Daily breakfast & dinner",
      "Private cab with driver for Jammu – Katra – Jammu",
      "Jammu railway station / airport pick-up and drop",
      "Assistance with yatra registration and route guidance",
      "Driver allowances, fuel, tolls & parking",
    ],
    exclusions: [
      "Train / airfare to Jammu",
      "Helicopter, pony, palki, battery car and ropeway tickets",
      "Offerings, prasad and donations at the shrine",
      "Lunch, personal expenses and porters",
      "Travel insurance",
      "GST, if applicable",
    ],
    itinerary: [
      {
        day: 1,
        title: "Arrive Jammu → Katra",
        description:
          "Pick-up from Jammu railway station or airport and drive about 50 km (1.5–2 hours) to Katra. Complete your yatra registration and rest — many pilgrims begin the climb late at night or early morning.",
        stay: "Katra",
      },
      {
        day: 2,
        title: "Yatra to Bhawan & Bhairon",
        description:
          "Walk the roughly 12–13 km path from Katra to the Bhawan, or use a pony, palki or battery car on sections. After darshan, continue to Bhairon temple on foot or by ropeway, then return to Katra to rest.",
        stay: "Katra",
      },
      {
        day: 3,
        title: "Katra → Jammu · Departure",
        description:
          "After breakfast, drive back to Jammu. Visit Raghunath Temple if time allows before your drop at the railway station or airport.",
      },
    ],
  },

  /* ------------------------------------------------------------------ */
  {
    slug: "jammu-katra-kashmir",
    title: "Jammu, Katra & Kashmir",
    subtitle: "Mata Vaishno Devi darshan, Patnitop, then the valleys of Kashmir",
    destinations: ["jammu", "katra", "srinagar", "gulmarg", "pahalgam"],
    region: "kashmir",
    categories: ["family", "pilgrimage"],
    nights: 8,
    days: 9,
    priceFrom: 27999,
    image: "/images/destinations/srinagar-5.jpg",
    highlights: [
      "Vaishno Devi darshan with two nights in Katra",
      "A night in the pine forests of Patnitop",
      "Scenic Jammu–Srinagar highway through the Banihal tunnel",
      "Dal Lake shikara ride and the Mughal Gardens",
      "Gulmarg Gondola day (tickets extra)",
      "Two nights in Pahalgam by the Lidder river",
    ],
    inclusions: [
      "8 nights' accommodation on twin sharing (houseboat / hotel as per itinerary)",
      "Daily breakfast & dinner",
      "Private cab with driver for the entire trip",
      "Jammu railway station / airport pick-up and Srinagar airport drop",
      "Sightseeing as per itinerary",
      "1-hour shikara ride on Dal Lake",
      "Assistance with Vaishno Devi yatra registration",
      "Driver allowances, fuel, tolls & parking",
    ],
    exclusions: [
      "Train / airfare",
      "Helicopter, pony, palki, battery car and ropeway tickets at Vaishno Devi",
      "Gulmarg Gondola tickets",
      "Pony rides and local union cabs at Gulmarg & Pahalgam",
      "Garden and monument entry fees",
      "Lunch, personal expenses and travel insurance",
      "GST, if applicable",
    ],
    itinerary: [
      {
        day: 1,
        title: "Arrive Jammu → Katra",
        description:
          "Pick-up in Jammu and drive to Katra (about 1.5–2 hours). Complete your yatra registration and rest for the climb.",
        stay: "Katra",
      },
      {
        day: 2,
        title: "Vaishno Devi darshan",
        description:
          "Trek to the Bhawan for darshan, continuing to Bhairon temple, then return to Katra. Ponies, palkis, battery cars and helicopter services are available at extra cost.",
        stay: "Katra",
      },
      {
        day: 3,
        title: "Katra → Patnitop",
        description:
          "A relaxed 3-hour drive into the hills to Patnitop (≈ 2,000 m). Afternoon walks in the deodar forest or a short excursion to Nathatop.",
        stay: "Patnitop",
      },
      {
        day: 4,
        title: "Patnitop → Srinagar",
        description:
          "Continue on the Jammu–Srinagar highway via Ramban, Banihal and the Navyug tunnel into the Kashmir valley (around 6–7 hours). Check in to your houseboat or hotel.",
        stay: "Srinagar",
      },
      {
        day: 5,
        title: "Srinagar sightseeing",
        description:
          "Nishat Bagh, Shalimar Bagh, Chashme Shahi and Hazratbal shrine, followed by an evening shikara ride on Dal Lake.",
        stay: "Srinagar",
      },
      {
        day: 6,
        title: "Gulmarg day trip",
        description:
          "Day excursion to Gulmarg (about 50 km each way). Ride the Gulmarg Gondola, enjoy the meadows or snow depending on season, and return to Srinagar.",
        stay: "Srinagar",
      },
      {
        day: 7,
        title: "Srinagar → Pahalgam",
        description:
          "Drive to Pahalgam via the saffron fields of Pampore and the Awantipora ruins. Evening by the Lidder river.",
        stay: "Pahalgam",
      },
      {
        day: 8,
        title: "Pahalgam valleys",
        description:
          "Visit Aru Valley, Betaab Valley and Chandanwari by local union cab, or enjoy a leisurely day in Pahalgam.",
        stay: "Pahalgam",
      },
      {
        day: 9,
        title: "Pahalgam → Srinagar airport",
        description:
          "Drive back to Srinagar (about 2.5–3 hours) for your drop at Srinagar airport.",
      },
    ],
  },

  /* ------------------------------------------------------------------ */
  {
    slug: "jammu-heritage-patnitop",
    title: "Jammu Heritage & Patnitop",
    subtitle: "The City of Temples, Dogra palaces and the meadows of Patnitop & Sanasar",
    destinations: ["jammu"],
    region: "jammu",
    categories: ["family", "group"],
    nights: 3,
    days: 4,
    priceFrom: 9999,
    image: "/images/destinations/jammu-2.jpg",
    highlights: [
      "Raghunath Temple and Ranbireshwar Temple",
      "Bahu Fort, Bawe Wali Mata temple and Bagh-e-Bahu",
      "Mubarak Mandi palace complex and Amar Mahal Palace",
      "Deodar forests and viewpoints of Patnitop",
      "Sanasar meadows — paragliding in season",
    ],
    inclusions: [
      "3 nights' accommodation on twin sharing (1 Jammu, 2 Patnitop)",
      "Daily breakfast & dinner",
      "Private cab with driver for the entire trip",
      "Jammu railway station / airport pick-up and drop",
      "Sightseeing as per itinerary",
      "Driver allowances, fuel, tolls & parking",
    ],
    exclusions: [
      "Train / airfare to Jammu",
      "Museum and monument entry fees",
      "Paragliding, horse rides and other activities",
      "Lunch and personal expenses",
      "Travel insurance",
      "GST, if applicable",
    ],
    itinerary: [
      {
        day: 1,
        title: "Arrive Jammu · City of Temples",
        description:
          "Pick-up from Jammu railway station or airport. Visit Raghunath Temple and Ranbireshwar Temple, then Bahu Fort and the Bawe Wali Mata temple, ending at the terraced Bagh-e-Bahu gardens above the Tawi.",
        stay: "Jammu",
      },
      {
        day: 2,
        title: "Dogra heritage → Patnitop",
        description:
          "Morning at the Mubarak Mandi palace complex and Amar Mahal Palace, former seats of the Dogra rulers. Then drive about 110 km (3–3.5 hours) up to Patnitop.",
        stay: "Patnitop",
      },
      {
        day: 3,
        title: "Sanasar & Nathatop",
        description:
          "Drive to the cup-shaped meadows of Sanasar, about 20 km away, for walks and paragliding in season. Stop at Nathatop viewpoint and the Nag temple on the way back.",
        stay: "Patnitop",
      },
      {
        day: 4,
        title: "Patnitop → Jammu · Departure",
        description:
          "Drive back to Jammu, stopping at Kud for its well-known patisa sweet, and drop at the railway station or airport.",
      },
    ],
  },
];

export function getPackage(slug: string) {
  return packages.find((p) => p.slug === slug);
}

export function getPackagesForDestination(destinationSlug: string) {
  return packages.filter((p) => p.destinations.includes(destinationSlug));
}

export const featuredPackages = () => packages.filter((p) => p.featured);
