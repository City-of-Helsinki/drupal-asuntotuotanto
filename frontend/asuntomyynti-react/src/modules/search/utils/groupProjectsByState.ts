import { Project } from '../../../types/common';

export const groupProjectsByState = (projects: Project[]) =>
  projects.reduce<{ [key: string]: Project[] }>((all, project) => {
    const key = project.state_of_sale;
    return {
      ...all,
      [key]: [...(all[key] || []), project],
    };
  }, {});
