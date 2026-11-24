import { Project } from '../../../types/common';

const mapSearchResults = (result: Array<Record<string, unknown>>) => {
  const firstHit = result[0] ?? {};
  const project: Record<string, unknown> = {};

  // Maps the Project entity
  Object.keys(firstHit).forEach((x: string) => {
    if (x.includes('project_')) {
      const key = x.split('project_')[1];
      project[key] = firstHit[x];
    }
  });

  return {
    ...(project as Project),
    apartments: result as unknown as Project['apartments'],
  };
};

export default mapSearchResults;
