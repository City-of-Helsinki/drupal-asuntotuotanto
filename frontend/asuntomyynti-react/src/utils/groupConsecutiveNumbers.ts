export const groupConsecutiveNumbers = (numbers: number[]): number[][] =>
  numbers.sort().reduce((groups: number[][], number) => {
    // Take the last set
    const lastSet: number[] = groups.pop() || [];
    // If set is empty or last number is consecutive add to set
    if (lastSet.length === 0 || lastSet[lastSet.length - 1] === number - 1) {
      return [...groups, [...lastSet, number]];
    }
    // Put it back with new set
    return [...groups, lastSet, [number]];
  }, []);

export const listGroupedNumbers = (
  groupedNumbers: number[][],
  format?: (first?: number, last?: number) => string
): string => {
  return groupedNumbers
    .reduce((lists: string[], numbers) => {
      const [first, ...rest] = numbers;
      const last = rest.pop();
      const list = `${first}${last ? '-' + last : ''}`;
      return [...lists, format ? list + format(first, last) : list];
    }, [])
    .join(', ');
};
