import { Project } from '../../../types/common';

export const filterProjectsByOwnershipType = (projects: Project[], key: string) => {
  // Get only projects that have ownership_type
  const projectsWithOwnershipType = projects.filter((project) => !!project.ownership_type);

  const isHaso = key.toLowerCase() === 'haso';

  if (isHaso) {
    // Return only haso projects
    return projectsWithOwnershipType.filter((project) => project.ownership_type.toLowerCase() === 'haso');
  } else {
    // Return both hitas and puolihitas projects
    return projectsWithOwnershipType.filter((project) => project.ownership_type.toLowerCase() !== 'haso');
  }
};
