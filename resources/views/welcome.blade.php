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
  </style>
<script>
    async function loadPlanes(){
        const response = await fetch('https://opensky-network.org/api/states/all').then(p=>p.json());
        const planeData = response["states"];
        return planeData.map(d => ({
            lat: d[6],
            lng: d[5],
            size: 13,
            color: 'red'
        }));
    }

    async function updatePlanePositions(world) {
        setInterval(async () => {
            const updatedData = await loadPlanes();
            world.htmlElementsData(updatedData);
        }, 60000);
        console.log("Plane positions updating every minute.");
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

    const markerSvg = `<svg viewBox="-4 0 36 36">
    <path fill="currentColor" d="M14,0 C21.732,0 28,5.641 28,12.6 C28,23.963 14,36 14,36 C14,36 0,24.064 0,12.6 C0,5.641 6.268,0 14,0 Z"></path>
    <circle fill="black" cx="14" cy="14" r="7"></circle>
  </svg>`;

    //const N = 30;
   // const gData = [...Array(N).keys()].map(() => ({
    //  lat: (Math.random() - 0.5) * 180,
   //   lng: (Math.random() - 0.5) * 360,
    //  size: 5,
   //   color: 'yellow'
  //  }));

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
        el.innerHTML = markerSvg;
        el.style.color = d.color;
        el.style.width = `${d.size}px`;
        el.style.transition = 'opacity 250ms';
        el.style['pointer-events'] = 'auto';
        el.style.cursor = 'pointer';
        el.onclick = () => console.info(d);
        return el;
      })
      .htmlElementVisibilityModifier((el, isVisible) => el.style.opacity = isVisible ? 1 : 0);

    
    updatePlanePositions(world);
    });
  </script>
</body>
</html>