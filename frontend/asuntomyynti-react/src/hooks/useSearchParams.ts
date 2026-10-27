import { useLocation } from 'react-router-dom';
import { useEffect, useState } from 'react';

const useSearchParams = () => {
  const [searchParams, setSearchParams] = useState(new URLSearchParams());
  const { search } = useLocation();

  useEffect(() => {
    setSearchParams(new URLSearchParams(search));
  }, [search]);

  return searchParams;
};

export default useSearchParams;
