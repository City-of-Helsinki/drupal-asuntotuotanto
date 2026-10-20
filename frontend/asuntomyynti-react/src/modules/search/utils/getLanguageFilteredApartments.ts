import { Apartment } from '../../../types/common';

export const getLanguageFilteredApartments = (apartments: Apartment[], currentLang: string) => {
  // Get a list of all the apartments with Finnish translations only
  const apartmentsByBaseLang = apartments.filter((x) => x._language === 'fi');

  // Get a list of all the apartments that have a translation for currently active language
  const apartmentsByCurrentLang = apartments.filter((x) => x._language === currentLang);

  // Combine a list of aparments where we use current language for each apartment if it's available,
  // otherwise use the fallback Finnish language
  const filteredApartments = apartmentsByBaseLang.map(
    (x) => apartmentsByCurrentLang.find((y) => y.uuid === x.uuid) || x
  );

  return filteredApartments;
};
