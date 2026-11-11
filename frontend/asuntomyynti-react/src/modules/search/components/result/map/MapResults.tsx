import cx from 'classnames';
import { Button, ButtonVariant, IconCross, IconLocation, IconMenuHamburger, IconSize, Tooltip } from 'hds-react';
import L from 'leaflet';
import type { Popup as LeafletPopup } from 'leaflet';
import { createRef, useRef, type RefObject } from 'react';
import ReactDOMServer from 'react-dom/server';
import { useTranslation } from 'react-i18next';
import { MapContainer, Marker, Popup, TileLayer, ZoomControl } from 'react-leaflet';

import useSessionStorageState from '../../../../../hooks/useSessionStorageState';
import { DataConfig, Project, StateOfSale } from '../../../../../types/common';
import { calculateApartmentCount } from '../../../utils/calculateApartmentCount';
import getInitialMapPosition from '../../../utils/getInitialMapPosition';
import MapProjectCard from './MapProjectCard';
import MapProjectPopupCard from './MapProjectPopupCard';

import css from './MapResults.module.scss';

type Props = {
  config: DataConfig | undefined;
  header: string;
  searchResults: Project[];
  closeMap: () => void;
  currentLang: string;
  resultCountByProjects?: boolean;
  hideApartments?: boolean;
  tooltipText?: string;
  showSubscribeButton?: boolean;
  description?: string;
};

const MAP_TILES_URL =
  import.meta.env.VITE_MAP_TILES_URL || 'https://tiles.hel.ninja/styles/hel-osm-bright/{z}/{x}/{y}.png';

const MapResults = ({
  config,
  header,
  searchResults,
  closeMap,
  currentLang,
  resultCountByProjects = false,
  hideApartments = false,
  tooltipText = '',
  showSubscribeButton = false,
  description = '',
}: Props) => {
  const { t } = useTranslation();
  const [activeProject, setActiveProject] = useSessionStorageState<Project | null>({
    defaultValue: null,
    key: 'MapResultsActiveProject',
  });
  const popupRef = useRef<Array<RefObject<LeafletPopup>>>([]);
  const activeProjectRef = useRef<HTMLDivElement>(null);
  popupRef.current = searchResults.map((_, i) => popupRef.current[i] ?? createRef<LeafletPopup>());

  const closePopups = (ref: RefObject<LeafletPopup>) => {
    if (ref.current) {
      ref.current.remove();
    }
  };

  const handleMarkerPopupClick = (targetProject: Project, ref: RefObject<LeafletPopup>) => {
    closePopups(ref);
    setActiveProject(targetProject);

    if (activeProjectRef.current) {
      activeProjectRef.current.focus();
    }
  };

  const hideProject = () => {
    setActiveProject(null);
  };

  const getMarkerColor = (state?: StateOfSale) => {
    switch (state) {
      case StateOfSale.PreMarketing:
        return '#000000';
      case StateOfSale.ForSale:
        return '#0000bf';
      default:
        return '#0000bf';
    }
  };

  const getMarkerIcon = (project: Project) => {
    const color = getMarkerColor(project.state_of_sale);
    const isActive = project.uuid === activeProject?.uuid;

    const element = (
      <div
        style={{
          marginLeft: -14,
          marginTop: -14,
          width: 40,
          height: 40,
          backgroundColor: isActive ? color : 'white',
          borderRadius: '50%',
          display: 'flex',
          justifyContent: 'center',
          alignItems: 'center',
        }}
      >
        <IconLocation
          size={IconSize.Medium}
          color={isActive ? 'white' : color}
          aria-label={t('SEARCH:aria-map-marker')}
        />
      </div>
    );

    const icon = L.divIcon({
      className: 'custom-icon',
      html: ReactDOMServer.renderToString(element),
    });

    return icon;
  };

  return (
    <section className={css.container} aria-label={header}>
      <header>
        <div className={css.titleContainer}>
          <h2>
            {header}
            {tooltipText && (
              <Tooltip className={css.inlineTooltip} buttonLabel={t('SEARCH:aria-info-tooltip')} placement={'auto'}>
                {tooltipText}
              </Tooltip>
            )}
          </h2>
          <div className={css.resultsCount}>
            {t('SEARCH:total')}{' '}
            {resultCountByProjects
              ? `${searchResults.length} ${t('SEARCH:projects')}`
              : `${calculateApartmentCount(searchResults, currentLang)} ${t('SEARCH:apartments')}`}
          </div>
          {/* Subscribing to project mailing list is commented out until we get the backend implementation
          {description && <div className={css.description}>{description}</div>}
          */}
        </div>
        <div className={cx(css.headerButtonWrapper, description && css.hasDescription)}>
          <Button
            className={css.showButton}
            variant={ButtonVariant.Secondary}
            iconStart={<IconMenuHamburger aria-hidden="true" />}
            onClick={closeMap}
          >
            {t('SEARCH:show-as-list')}
          </Button>
        </div>
      </header>

      <div className={css.mapContainer}>
        <div className={css.mapWrapper} id={'asuReactMap'}>
          <MapContainer
            center={getInitialMapPosition(searchResults)}
            zoom={12}
            maxBounds={[
              [59.4, 23.8],
              [61.5, 25.8],
            ]}
            dragging={!L.Browser.mobile}
            zoomControl={false}
          >
            {!L.Browser.mobile && <ZoomControl position="bottomright" />}
            <TileLayer
              attribution='&amp;copy <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
              url={MAP_TILES_URL}
            />
            {searchResults.map((x, i) =>
              x.coordinate_lat && x.coordinate_lon ? (
                <Marker
                  key={x.uuid}
                  icon={getMarkerIcon(x)}
                  position={[x.coordinate_lat, x.coordinate_lon]}
                  title={x.housing_company}
                >
                  <Popup
                    ref={popupRef.current[i]}
                    closeButton={false}
                    className={css.popup}
                    eventHandlers={{
                      popupopen: () => hideProject()
                    }}
                  >
                    <MapProjectPopupCard
                      config={config}
                      project={x}
                      currentLang={currentLang}
                      onCloseBtnClick={() => closePopups(popupRef.current[i])}
                      onApartmentsBtnClick={() => handleMarkerPopupClick(x, popupRef.current[i])}
                      hideApartments={hideApartments}
                      activeProject={activeProject}
                      showSubscribeButton={showSubscribeButton}
                    />
                  </Popup>
                </Marker>
              ) : null
            )}
          </MapContainer>
          <div ref={activeProjectRef} tabIndex={-1}>
            {!hideApartments && activeProject && (
              <div
                className={css.activeProjectWrapper}
                id={`map-project-popup-${activeProject.id}`}
                aria-label={activeProject.housing_company}
              >
                <button
                  className={css.closeIcon}
                  onClick={() => hideProject()}
                  aria-label={t('SEARCH:aria-hide-project-popup')}
                >
                  <IconCross aria-hidden="true" />
                </button>
                <div className={css.activeProjectDetails}>
                  <MapProjectCard config={config} project={activeProject} currentLang={currentLang} />
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </section>
  );
};

export default MapResults;
