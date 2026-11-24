import { MutableRefObject, useEffect } from 'react';

// Make any element listen ourside clicks
const useOutsideClick = (ref: MutableRefObject<HTMLElement | null>, callback: () => void) => {
  useEffect(() => {
    const handleClick = (event: MouseEvent) => {
      // Is the click outside this component
      if (ref.current && event.target instanceof Node && !ref.current.contains(event.target)) {
        callback();
      }
    };

    document.addEventListener('click', handleClick);

    return () => {
      document.removeEventListener('click', handleClick);
    };
  }, [callback, ref]);
};

export default useOutsideClick;
