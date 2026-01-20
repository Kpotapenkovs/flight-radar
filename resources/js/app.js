import './bootstrap';

import Globe from 'globe.gl';

const myGlobe = new Globe(myDOMElement)
  .globeImageUrl(myImageUrl)
  .pointsData(myData);