import axios from 'axios';
import { useQuery } from '@tanstack/react-query';
import { enhanceFilterConfig } from '../../../utils/enhanceFilterConfig';

const DAY_IN_SECONDS = 86400;

const initializePath = import.meta.env.VITE_INIT_PATH || 'initialize';

const useDataConfig = (language: string) => {
  const fetchDataConfig = async () => {
    const { data } = await axios.get(`/${language}/${initializePath}`);
    data.filters = enhanceFilterConfig(data.filters);
    return data;
  };

  return useQuery({
    queryKey: ['dataConfig', language],
    queryFn: fetchDataConfig,
    staleTime: DAY_IN_SECONDS * 1000, // Convert to milliseconds
  });
};

export default useDataConfig;
