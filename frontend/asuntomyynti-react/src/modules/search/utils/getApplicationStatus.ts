import { ProjectApplicationStatusConfig, ApartmentApplicationStatusConfig } from '../../../types/common';

export const getProjectApplicationStatus = (status: ProjectApplicationStatusConfig | undefined, id: number) => {
  if (!status) {
    return undefined;
  }
  const statusByProjectId = status[id];
  return statusByProjectId;
};

export const getApartmentApplicationStatus = (status: ApartmentApplicationStatusConfig | undefined, id: number) => {
  if (!status) {
    return undefined;
  }
  const statusByApartmentId = status[id];
  return statusByApartmentId;
};
