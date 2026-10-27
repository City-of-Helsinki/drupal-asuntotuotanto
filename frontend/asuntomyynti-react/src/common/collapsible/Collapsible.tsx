import { HTMLProps, useCallback } from 'react';
import { useEffect, useRef } from 'react';
import styles from './Collapsible.module.scss';
import useSessionStorageState from '../../hooks/useSessionStorageState';

type Props = {
  id: string;
  expand: boolean;
  onCollapse: () => void;
} & HTMLProps<HTMLDivElement>;

const Collapsible: React.FunctionComponent<Props> = ({ id, expand, children, onCollapse, ...rest }) => {
  const [height, setHeight] = useSessionStorageState({ defaultValue: 0, key: `collapsible-${id}` });

  const ref = useRef<HTMLDivElement>(null);
  const timer = useRef<NodeJS.Timeout | null>(null);

  const updateHeight = useCallback(() => {
    if (expand) {
      const { clientHeight } = ref.current || {};
      setHeight(clientHeight || 0);
    } else {
      setHeight(0);
    }
  }, [expand, setHeight]);

  /**
   * Debounce to prevent too much re-rendering
   */
  const handleResize = useCallback(() => {
    if (timer.current) {
      clearTimeout(timer.current);
    }
    timer.current = setTimeout(() => {
      updateHeight();
    }, 250);
  }, [updateHeight]);

  const handleTransitionEnd = useCallback(() => {
    if (height === 0 && typeof onCollapse === 'function') {
      onCollapse();
    }
  }, [height, onCollapse]);

  // Add listener for resize event
  useEffect(() => {
    window.addEventListener('resize', handleResize);
    window.addEventListener('transitionend', handleTransitionEnd);
    return () => {
      window.removeEventListener('resize', handleResize);
      window.removeEventListener('transitionend', handleTransitionEnd);
    };
  }, [handleResize, handleTransitionEnd]);

  // Update height when expanded or children changes
  useEffect(() => {
    updateHeight();
  }, [expand, children, updateHeight]);

  return (
    <div {...rest} id={id}>
      <div className={styles.collapsible} style={{ height }}>
        <div ref={ref}>{children}</div>
      </div>
    </div>
  );
};

export default Collapsible;
