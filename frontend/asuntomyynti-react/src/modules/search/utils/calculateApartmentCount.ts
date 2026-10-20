import { Project } from '../../../types/common';
import { getLanguageFilteredApartments } from './getLanguageFilteredApartments';

export const calculateApartmentCount = (projects: Project[], currentLang: string) => {
  if (!projects.length) {
    return 0;
  }

  // Get apartment list for each project
  const projectApartments = projects.map((project) => project.apartments);

  if (!projectApartments.length) {
    return 0;
  }

  // Merge arrays of project apartments
  const apartments = projectApartments.reduce((a, b) => a.concat(b));

  // Get apartments by currenet language
  const filteredApartments = getLanguageFilteredApartments(apartments, currentLang);

  return filteredApartments.length;
};
