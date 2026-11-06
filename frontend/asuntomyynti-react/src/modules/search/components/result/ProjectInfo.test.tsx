import { render, screen } from '@testing-library/react';
import ProjectInfo from './ProjectInfo';

import mockProject from '../../mocks/single-project.json';
import { Project } from '../../../../types/common';

const project = mockProject as unknown as Project;

describe('ProjectInfo', () => {
  it('renders the component', () => {
    const { container } = render(<ProjectInfo project={project} userHasApplications={false} />);
    const element = container.firstChild;
    expect(element).toBeDefined();
  });

  it('has user applications', () => {
    render(<ProjectInfo project={project} userHasApplications applicationUrl="https://example.test" />);
    expect(screen.getByText('SEARCH:user-application-project')).toBeDefined();
  });

  it('does not have user applications', () => {
    render(<ProjectInfo project={project} userHasApplications={false} applicationUrl="https://example.test" />);
    expect(screen.queryByText('SEARCH:user-application-project')).toBeNull();
  });

  it('does have estimated completion', () => {
    render(<ProjectInfo project={project} userHasApplications={false} />);
    expect(screen.getByText('Kesä 2021')).toBeDefined();
  });

  it('does not have estimated completion', () => {
    render(<ProjectInfo project={{ ...project, estimated_completion: '' }} userHasApplications={false} />);
    expect(screen.queryByText('Kesä 2021')).toBeNull();
  });

  it('does have possession transfer date', () => {
    render(<ProjectInfo project={project} userHasApplications={false} />);
    expect(screen.getByText('SEARCH:move-in-date', { exact: false })).toBeDefined();
  });

  it('does not have possession transfer date', () => {
    render(<ProjectInfo project={{ ...project, possession_transfer_date: '' }} userHasApplications={false} />);
    expect(screen.queryByText('SEARCH:move-in-date', { exact: false })).toBeNull();
  });

  it('does have upcoming description', () => {
    render(<ProjectInfo project={project} userHasApplications={false} />);
    expect(screen.getByText('Kohde on suunnitteilla ja tulossa hakuun')).toBeDefined();
  });

  it('does not have upcoming description', () => {
    render(<ProjectInfo project={{ ...project, upcoming_description: '' }} userHasApplications={false} />);
    expect(screen.queryByText('Kohde on suunnitteilla ja tulossa hakuun')).toBeNull();
  });
});
