<!DOCTYPE html>
<html lang="en">
<head>
  <style>
    body { margin: 0; }

    #time {
      position: absolute;
      bottom: 8px;
      left: 8px;
      color: lightblue;
      font-family: monospace;
    }
    .planeData{
      display: absolute;
      background-color: white;
      justify-content: center;
      align-items: center;
      height: 100px;
      width: 300px;
    }
  </style>
<script>
  async function loadPlanes() { 
    const response = await fetch('https://opensky-network.org/api/states/all').then(p => p.json());
    let planeData = await response["states"];



    return planeData
    .filter(d => d[6] !== null && d[5] !== null)
    .map(d => ({
      lat: d[6],
      lng: d[5],
      size: 5,
      color: d[8] === true ? 'red' : 'white',
      heading: d[10] * 90
    }));

    const markerSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" transform="rotate()" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-plane"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M16 10h4a2 2 0 0 1 0 4h-4l-4 7h-3l2 -7h-4l-2 2h-3l2 -4l-2 -4h3l2 2h4l-2 -7h3l4 7" /></svg>
    `;

  }
  async function updatePlanePositions(world) {
    setInterval(async () => {
      const updatedData = await loadPlanes();
      world.htmlElementsData(updatedData);
    }, 60000);
  }

  function isMarkerVisible(lat, lng, globeRotation) {
    const toRad = angle => angle * Math.PI / 180;
    const markerVector = new THREE.Vector3(
      Math.cos(toRad(lat)) * Math.cos(toRad(lng)),
      Math.sin(toRad(lat)),
      Math.cos(toRad(lat)) * Math.sin(toRad(lng))
    );
    const globeVector = new THREE.Vector3(
      Math.cos(toRad(globeRotation.y)) * Math.cos(toRad(globeRotation.x)),
      Math.sin(toRad(globeRotation.y)),
      Math.cos(toRad(globeRotation.y)) * Math.sin(toRad(globeRotation.x))
    );
    return markerVector.dot(globeVector) > 0;
  }
</script>

<script src="//cdn.jsdelivr.net/npm/globe.gl"></script>
<!--  <script src="../../dist/globe.gl.js"></script>-->
</head>

<body>

  <div id="globeViz"></div>
  <div id="time"></div>

  <script type="module">
    import { TextureLoader, ShaderMaterial, Vector2 } from 'https://esm.sh/three';
    import * as solar from 'https://esm.sh/solar-calculator';

    const VELOCITY = 1; // minutes per frame

    // Custom shader:  Blends night and day images to simulate day/night cycle
    const dayNightShader = {
      vertexShader: `
        varying vec3 vNormal;
        varying vec2 vUv;
        void main() {
          vNormal = normalize(normalMatrix * normal);
          vUv = uv;
          gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
        }
      `,
      fragmentShader: `
        #define PI 3.141592653589793
        uniform sampler2D dayTexture;
        uniform sampler2D nightTexture;
        uniform vec2 sunPosition;
        uniform vec2 globeRotation;
        varying vec3 vNormal;
        varying vec2 vUv;

        float toRad(in float a) {
          return a * PI / 180.0;
        }

        vec3 Polar2Cartesian(in vec2 c) { // [lng, lat]
          float theta = toRad(90.0 - c.x);
          float phi = toRad(90.0 - c.y);
          return vec3( // x,y,z
            sin(phi) * cos(theta),
            cos(phi),
            sin(phi) * sin(theta)
          );
        }

        void main() {
          float invLon = toRad(globeRotation.x);
          float invLat = -toRad(globeRotation.y);
          mat3 rotX = mat3(
            1, 0, 0,
            0, cos(invLat), -sin(invLat),
            0, sin(invLat), cos(invLat)
          );
          mat3 rotY = mat3(
            cos(invLon), 0, sin(invLon),
            0, 1, 0,
            -sin(invLon), 0, cos(invLon)
          );
          vec3 rotatedSunDirection = rotX * rotY * Polar2Cartesian(sunPosition);
          float intensity = dot(normalize(vNormal), normalize(rotatedSunDirection));
          vec4 dayColor = texture2D(dayTexture, vUv);
          vec4 nightColor = texture2D(nightTexture, vUv);
          float blendFactor = smoothstep(-0.1, 0.1, intensity);
          gl_FragColor = mix(nightColor, dayColor, blendFactor);
        }
      `
    };

    const sunPosAt = dt => {
      const day = new Date(+dt).setUTCHours(0, 0, 0, 0);
      const t = solar.century(dt);
      const longitude = (day - dt) / 864e5 * 360 - 180;
      return [longitude - solar.equationOfTime(t) / 4, solar.declination(t)];
    };


// ----------- Marker SVG -----------





  const gData = await loadPlanes();

// -------------- Initial time --------------
    let dt = +new Date();
    const timeEl = document.getElementById('time');

    const world = new Globe(document.getElementById('globeViz'));



// ----------  diennakts maiņa  ----------

    Promise.all([
      new TextureLoader().loadAsync('//cdn.jsdelivr.net/npm/three-globe/example/img/earth-day.jpg'),
      new TextureLoader().loadAsync('//cdn.jsdelivr.net/npm/three-globe/example/img/earth-night.jpg')
    ]).then(([dayTexture, nightTexture]) => {
      const material = new ShaderMaterial({
        uniforms: {
          dayTexture: { value: dayTexture },
          nightTexture: { value: nightTexture },
          sunPosition: { value: new Vector2() },
          globeRotation: { value: new Vector2() }
        },
        vertexShader: dayNightShader.vertexShader,
        fragmentShader: dayNightShader.fragmentShader
      });



    // ----------  laika josla  ----------

      world.globeMaterial(material)
        .backgroundImageUrl('//cdn.jsdelivr.net/npm/three-globe/example/img/night-sky.png')
        // Update globe rotation on shader
        .onZoom(({ lng, lat }) => material.uniforms.globeRotation.value.set(lng, lat));

      requestAnimationFrame(() =>
        (function animate() {
          // animate time of day
          dt += Date.now() - dt < 100 ? VELOCITY * 60000 : 0;
          timeEl.textContent = new Date(dt).toLocaleString();
          material.uniforms.sunPosition.value.set(...sunPosAt(dt));
          requestAnimationFrame(animate);
        })()
      );




      // ----------  izvadīšana  ----------
    world
      .htmlElementsData(gData)
      .htmlElement(d => {
        const el = document.createElement('div');
        el.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" transform="rotate(${d.heading})" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-plane"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M16 10h4a2 2 0 0 1 0 4h-4l-4 7h-3l2 -7h-4l-2 2h-3l2 -4l-2 -4h3l2 2h4l-2 -7h3l4 7" /></svg>
        `;
        el.style.color = d.color;
        el.style.width = `${d.size}px`;
        el.style.transition = 'opacity 250ms';
        el.style['pointer-events'] = 'auto';
        el.style.cursor = 'pointer';
        el.onclick = () => {
          el.classList.toggle('planeData');
        };
        return el;
      })
      .htmlElementVisibilityModifier((el, isVisible) => el.style.opacity = isVisible ? 1 : 0);

    
    updatePlanePositions(world);
    });
  </script>
</body>
</html>