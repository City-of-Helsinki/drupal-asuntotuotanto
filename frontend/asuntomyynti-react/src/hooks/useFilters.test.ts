import { act, renderHook } from '@testing-library/react';
import { createElement } from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi } from 'vitest';
import useFilters from './useFilters';

const mockedNavigate = vi.fn();

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockedNavigate,
  };
});

const wrapper = ({ children }: { children: React.ReactNode }) =>
  createElement(MemoryRouter, { initialEntries: ['/'] }, children);

describe('useFilters', () => {
  beforeEach(() => {
    mockedNavigate.mockReset();
  });

  it('uses replace navigation when setting a filter', () => {
    const { result } = renderHook(() => useFilters(), { wrapper });

    act(() => {
      result.current.setFilter('room_count', '1');
    });

    expect(mockedNavigate).toHaveBeenCalledWith('?room_count=1', { replace: true });
  });

  it('uses replace navigation for multiple filter operations', () => {
    const { result } = renderHook(() => useFilters(), { wrapper });

    act(() => {
      result.current.setFilter('room_count', '1');
      result.current.setFilter('room_count', '1,2');
      result.current.addFilter('living_area', '50,80');
      result.current.clearFilter('room_count');
    });

    expect(mockedNavigate).toHaveBeenCalledTimes(4);
    expect(mockedNavigate).toHaveBeenNthCalledWith(1, '?room_count=1', { replace: true });
    expect(mockedNavigate).toHaveBeenNthCalledWith(2, '?room_count=1%2C2', { replace: true });
    expect(mockedNavigate).toHaveBeenNthCalledWith(3, '?room_count=1%2C2&living_area=50%2C80', { replace: true });
    expect(mockedNavigate).toHaveBeenNthCalledWith(4, '?living_area=50%2C80', { replace: true });
  });

  it('uses replace navigation when removing one filter value', () => {
    const { result } = renderHook(() => useFilters(), { wrapper });

    act(() => {
      result.current.setFilter('room_count', '1,2,3');
      result.current.removeFilter('room_count', '2');
    });

    expect(mockedNavigate).toHaveBeenNthCalledWith(2, '?room_count=1%2C3', { replace: true });
  });

  it('uses replace navigation when clearing all filters', () => {
    const { result } = renderHook(() => useFilters(), { wrapper });

    act(() => {
      result.current.setFilter('room_count', '1');
      result.current.setFilter('living_area', '50,80');
      result.current.clearAllFilters({
        room_count: {} as any,
        living_area: {} as any,
      });
    });

    expect(mockedNavigate).toHaveBeenLastCalledWith('?', { replace: true });
  });

  it('clears properties with clearAllFilters', () => {
    const { result } = renderHook(() => useFilters(), { wrapper });

    act(() => {
      result.current.setFilter('properties', 'TOP_FLOOR,HAS_BALCONY');
      result.current.clearAllFilters({
        properties: {} as any,
      });
    });

    expect(mockedNavigate).toHaveBeenNthCalledWith(1, '?properties=TOP_FLOOR%2CHAS_BALCONY', { replace: true });
    expect(mockedNavigate).toHaveBeenNthCalledWith(2, '?', { replace: true });
  });
});