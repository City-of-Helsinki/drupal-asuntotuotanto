import { Project } from '../../../types/common';

const HKI_LONGITUDE = 24.94;
const HKI_LATITUDE = 60.17;

// Centers the map to middle of all the projects on initial render
const getInitialPosition = (searchResults: Project[]): [number, number] => {
  if (searchResults.length > 0) {
    let maxLon: number = searchResults[0].coordinate_lon || HKI_LONGITUDE;
    let minLon: number = searchResults[0].coordinate_lon || HKI_LONGITUDE;
    let maxLat: number = searchResults[0].coordinate_lat || HKI_LATITUDE;
    let minLat: number = searchResults[0].coordinate_lat || HKI_LATITUDE;

    searchResults.forEach((x) => {
      const { coordinate_lon, coordinate_lat } = x;

      if (maxLon < coordinate_lon) {
        maxLon = coordinate_lon;
      }
      if (!minLon || minLon > coordinate_lon) {
        minLon = coordinate_lon;
      }
      if (!maxLat || maxLat < coordinate_lat) {
        maxLat = coordinate_lat;
      }
      if (!maxLat || maxLat < coordinate_lat) {
        minLat = coordinate_lat;
      }
    });

    const lon = (maxLon - minLon) / 2 + minLon;
    const lat = (maxLat - minLat) / 2 + minLat;

    return [lat, lon];
  }

  return [HKI_LATITUDE, HKI_LONGITUDE];
};

export default getInitialPosition;
