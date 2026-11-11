import type { OriginCity, GeoPoint } from "@/types";

/** Where guests fly in from — drives the "Flying from" selector and the globe's flight arc. */
export const originCities: OriginCity[] = [
  { code: "DEL", city: "Delhi", geo: { lat: 28.5562, lng: 77.1 } },
  { code: "BOM", city: "Mumbai", geo: { lat: 19.0896, lng: 72.8656 } },
  { code: "BLR", city: "Bengaluru", geo: { lat: 13.1986, lng: 77.7066 } },
  { code: "MAA", city: "Chennai", geo: { lat: 12.9941, lng: 80.1709 } },
  { code: "CCU", city: "Kolkata", geo: { lat: 22.6547, lng: 88.4467 } },
  { code: "HYD", city: "Hyderabad", geo: { lat: 17.2403, lng: 78.4294 } },
  { code: "AMD", city: "Ahmedabad", geo: { lat: 23.0734, lng: 72.6266 } },
  { code: "PNQ", city: "Pune", geo: { lat: 18.5793, lng: 73.9089 } },
  { code: "DXB", city: "Dubai", geo: { lat: 25.2532, lng: 55.3657 } },
];

/** Sheikh ul-Alam International Airport, Srinagar — every arc lands here. */
export const srinagarAirport: GeoPoint & { code: string } = {
  code: "SXR",
  lat: 33.9871,
  lng: 74.7742,
};
