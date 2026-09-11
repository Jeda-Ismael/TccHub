import { Suspense, useEffect, useState } from 'react';
import { Canvas } from '@react-three/fiber';
import { Shadow, meshBounds, useGLTF, useTexture } from '@react-three/drei';
import { SpringValue, animated, useSpring } from '@react-spring/web';
import { a } from '@react-spring/three';
import { GraduationCapIcon } from './icons/GraduationCapIcon';

// Porte fiel do demo oficial do react-spring (pmndrs/react-spring —
// demo/src/sandboxes/webgl-switch): a mesma spring dirige a cena 3D E o
// fundo/texto do container ao redor. Cores e proporções seguem o original;
// só o conteúdo dos dois "<h1>" foi trocado por Aprovado/Reprovado e pela
// nota, no lugar do texto de exemplo "<h1>" / número crescente.
function SwitchMesh({ x, onClick }: { x: SpringValue<number>; onClick: () => void }) {
  const { nodes, materials } = useGLTF('/switch.glb') as any;
  const texture = useTexture('/cross.jpg');
  const [hovered, setHover] = useState(false);

  useEffect(() => {
    document.body.style.cursor = hovered ? 'pointer' : 'auto';
  }, [hovered]);

  const pZ = x.to([0, 1], [-1.2, 1.2]);
  const rX = x.to([0, 1], [0, Math.PI * 1.3]);
  const trackColor = x.to([0, 1], ['#888', '#2a2a2a']);

  return (
    <group scale={[1.25, 1.25, 1.25]} dispose={null}>
      <a.mesh
        receiveShadow
        castShadow
        material={materials.track}
        geometry={nodes.Cube.geometry}
        material-color={trackColor}
        material-roughness={0.5}
        material-metalness={0.8}
      />
      <a.group position-y={0.85} position-z={pZ}>
        <a.mesh
          receiveShadow
          castShadow
          raycast={meshBounds}
          rotation-x={rX}
          onClick={onClick}
          onPointerOver={() => setHover(true)}
          onPointerOut={() => setHover(false)}
        >
          <sphereGeometry args={[0.8, 64, 64]} />
          <a.meshStandardMaterial roughness={0.5} map={texture} />
        </a.mesh>
        <a.pointLight intensity={100} distance={1.4} color={trackColor} />
        {/* @ts-ignore — tipos do drei nesta versão exigem props que a Shadow não usa em runtime */}
        <Shadow renderOrder={-1000} position={[0, -1, 0]} rotation={[-Math.PI / 2, 0, 0]} scale={1.5} />
      </a.group>
    </group>
  );
}

export function WebGLSwitchPanel() {
  const [toggle, setToggle] = useState(0);
  const approved = toggle === 0;
  const [{ x }] = useSpring({ x: toggle, config: { mass: 5, tension: 1000, friction: 50, precision: 0.0001 } }, [toggle]);
  const onToggle = () => setToggle((t) => Number(!t));

  const lightColor = x.to([0, 1], ['#7fffd4', '#c72f46']);
  const bg = x.to([0, 1], ['#c9ffed', '#ff2558']);
  const fg = x.to([0, 1], ['#7fffd4', '#c70f46']);

  return (
    <animated.div className="switch-demo-container" style={{ backgroundColor: bg, color: fg }}>
      <h1 className="switch-demo-open">{approved ? 'Aprovado' : 'Reprovado'}</h1>
      <h1 className="switch-demo-close">{approved ? 'Aprovado' : 'Reprovado'}</h1>
      <animated.h1 className="switch-demo-score">{x.to((v) => (10 - v * 10).toFixed(2))}</animated.h1>
      <Canvas orthographic shadows dpr={[1, 2]} camera={{ zoom: 60, position: [-10, 10, 10], fov: 35 }}>
        <ambientLight intensity={0.1} />
        <directionalLight position={[-20, 20, 20]} intensity={1} />
        <a.directionalLight position={[-20, -20, -20]} intensity={0.5} color={lightColor} />
        <a.pointLight position={[0, 0, 5]} distance={5} intensity={5} color={lightColor} />
        <a.spotLight
          color={lightColor}
          position={[10, 20, 20]}
          angle={0.1}
          intensity={2}
          shadow-mapSize-width={2048}
          shadow-mapSize-height={2048}
          shadow-bias={-0.00001}
          castShadow
        />
        <Suspense fallback={null}>
          <SwitchMesh x={x} onClick={onToggle} />
        </Suspense>
        <mesh receiveShadow renderOrder={1000} position={[0, 0, 0]} rotation={[-Math.PI / 2, 0, 0]}>
          <planeGeometry args={[10, 10]} />
          <a.shadowMaterial transparent opacity={x.to((v) => 0.1 + v * 0.2)} />
        </mesh>
      </Canvas>

      {/* Texto fixo por cima do canvas — nunca fica escondido atrás da esfera */}
      <div className="switch-demo-topbar">
        <div className="switch-demo-mark">
          <GraduationCapIcon size={22} />
          <span>hubtcc</span>
        </div>
        <p className="switch-demo-sub">
          Plataforma oficial para submissão, orientação e avaliação de Trabalhos de Conclusão de Curso.
        </p>
      </div>

      <div className="switch-demo-bottom-status">
        <p className="switch-demo-foot">Acesso restrito a alunos, orientadores, avaliadores e coordenação.</p>
      </div>
    </animated.div>
  );
}
