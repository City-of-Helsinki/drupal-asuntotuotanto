import { filterProjectsByOwnershipType } from './filterProjectsByOwnershipType';

const mockProjects = [
  {
    ownership_type: 'hitas',
  },
  {
    ownership_type: 'puolihitas',
  },
  {
    ownership_type: 'haso',
  },
];

test('returns only haso projects', () => {
  const result = filterProjectsByOwnershipType(mockProjects, 'haso');
  expect(result).toEqual([{ ownership_type: 'haso' }]);
});

test('returns both hitas and puolihitas projects', () => {
  const result = filterProjectsByOwnershipType(mockProjects, 'hitas');
  expect(result).toEqual([{ ownership_type: 'hitas' }, { ownership_type: 'puolihitas' }]);
});
