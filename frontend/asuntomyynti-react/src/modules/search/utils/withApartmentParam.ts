/**
 * Add the apartment node id to an application form url.
 *
 * Used by the HITAS post-period reservation flow, where the customer picks a
 * single apartment in the search UI and the application form preselects it.
 */
export const withApartmentParam = (url: string, apartmentId: number) => {
  if (!url) {
    return url;
  }

  const [withoutHash, hash] = url.split('#');
  const [base, existingQuery = ''] = withoutHash.split('?');
  const params = new URLSearchParams(existingQuery);
  params.set('apartment', String(apartmentId));

  const hashSuffix = hash !== undefined ? `#${hash}` : '';
  return `${base}?${params.toString()}${hashSuffix}`;
};
