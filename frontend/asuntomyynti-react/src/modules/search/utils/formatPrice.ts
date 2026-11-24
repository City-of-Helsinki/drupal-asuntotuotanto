export const formattedPrice = (value: number) => {
  const calculatedPrice = value;
  return `${calculatedPrice.toLocaleString('fi-FI')} \u20AC`;
};

export default formattedPrice;
