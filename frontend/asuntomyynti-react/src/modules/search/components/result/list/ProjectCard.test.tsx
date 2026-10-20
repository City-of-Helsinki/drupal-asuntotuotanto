import { render, screen } from '@testing-library/react';
import ProjectCard from './ProjectCard';

import mockProject from '../../../mocks/single-project.json';
import mockConfig from '../../../mocks/filter-config.json';

const project = mockProject as any;
const config = mockConfig as any;

const defaultProps = {
  project,
  config,
  currentLang: 'fi',
};

test('renders ProjectCard component', () => {
  const { container } = render(<ProjectCard {...defaultProps} />);
  const element = container.firstChild;
  expect(element).toBeDefined();
});

test('renders ProjectCard component with proper data', () => {
  render(<ProjectCard {...defaultProps} />);

  expect(screen.getByText('Pellervontie 24')).toBeDefined();
  expect(screen.getByText('Hitas')).toBeDefined();
  expect(screen.getByText('Asunto Oy Tuleva S')).toBeDefined();
});

test('Apartment list toggle button is hidden', () => {
  render(<ProjectCard {...defaultProps} hideApartments={true} />);

  expect(screen.queryByText('SEARCH:apartments')).toBeNull();
});

test('Apartment list toggle button is shown', () => {
  render(<ProjectCard {...defaultProps} hideApartments={false} />);

  expect(screen.queryByText('1 SEARCH:apartments')).not.toBeNull();
});

describe('ES:showing_times in project card', () => {
  test('renders first showing_time_ranges display once under address', () => {
    const hasoProject = {
      ...project,
      ownership_type: 'haso',
      apartments: [
        {
          ...project.apartments[0],
          showing_time_ranges: [
            { start: '2026-10-14T13:03:00+03:00', end: '2026-10-14T13:58:00+03:00', display: '14.10.2026 13:03 - 13:58' },
            { start: '2026-10-14T14:03:00+03:00', end: '2026-10-14T14:58:00+03:00', display: '14.10.2026 14:03 - 14:58' },
          ],
          showing_times: ['2026-10-07T06:30:00+03:00'],
        },
      ],
    };

    render(<ProjectCard {...defaultProps} project={hasoProject} />);

    expect(screen.getByText('ES:showing_times: 14.10.2026 13:03 - 13:58')).toBeInTheDocument();
    expect(screen.getAllByText(/ES:showing_times:/)).toHaveLength(1);
  });

  test('falls back to showing_times[0] when showing_time_ranges display is empty', () => {
    const hasoProject = {
      ...project,
      ownership_type: 'haso',
      apartments: [
        {
          ...project.apartments[0],
          showing_time_ranges: [{ start: '2026-10-14T13:03:00+03:00', end: '2026-10-14T13:58:00+03:00', display: '' }],
          showing_times: ['2026-10-07T06:30:00+03:00'],
        },
      ],
    };

    render(<ProjectCard {...defaultProps} project={hasoProject} />);

    expect(screen.getByText('ES:showing_times: 07.10.2026 06:30')).toBeInTheDocument();
  });

  test('does not render when both sources are empty', () => {
    const hasoProject = {
      ...project,
      ownership_type: 'haso',
      apartments: [
        {
          ...project.apartments[0],
          showing_time_ranges: null,
          showing_times: null,
        },
      ],
    };

    render(<ProjectCard {...defaultProps} project={hasoProject} />);

    expect(screen.queryByText(/ES:showing_times:/)).toBeNull();
  });
});
