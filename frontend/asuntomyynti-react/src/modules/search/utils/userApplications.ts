import { ApplicationStatus, DataConfig, UserConfig } from '../../../types/common';

export const userHasApplications = (user: UserConfig | undefined, id: number) => {
  if (!user) {
    return false;
  }
  const hasApplications = id in user.applications;
  return hasApplications;
};

export const getUserApplications = (user: UserConfig | undefined, id: number) => {
  if (!user) {
    return undefined;
  }
  const applicationsById = user.applications[id];
  return applicationsById;
};

export const userHasApplicationForApartment = (applications: number[] | undefined, id: number) => {
  if (!applications) {
    return false;
  }
  return applications.includes(id);
};

/**
 * Whether the user already has a reserved or sold apartment in the project.
 *
 * Used to hide apply / after-apply / make-reservation CTAs for other apartments
 * in the same project.
 */
export const userHasReservedOrSoldApartment = (
  data: DataConfig | undefined,
  projectId: number
): boolean => {
  if (!data) {
    return false;
  }

  const user = data.user;
  if (!data.apartment_application_status || !user?.applications) {
    return false;
  }

  const userApartmentIds = user.applications[projectId];
  if (!userApartmentIds?.length) {
    return false;
  }

  const projectStatuses = data.apartment_application_status[projectId];
  if (!projectStatuses) {
    return false;
  }

  const blockingStatuses = [
    ApplicationStatus.Sold.valueOf(),
    ApplicationStatus.Reserved.valueOf(),
    ApplicationStatus.ReservedHaso.valueOf(),
  ];

  return userApartmentIds.some(
    (apartmentId) => blockingStatuses.indexOf(projectStatuses[apartmentId]) !== -1
  );
};
