import { useState } from 'react';

export default function ExampleIsland({
  message = 'Reactive Client State Hydrated',
  runtime = 'RoadRunner',
  timestamp = '',
}) {
  const [count, setCount] = useState(0);

  return (
    <div style={{
      display: 'flex',
      flexWrap: 'wrap',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: '1rem',
      background: 'rgba(18, 18, 22, 0.8)',
      border: '1px solid rgba(255, 255, 255, 0.08)',
      borderRadius: '12px',
      padding: '1.25rem 1.5rem',
      fontFamily: 'system-ui, -apple-system, sans-serif',
      textAlign: 'left',
    }}>
      <div style={{ display: 'flex', flexDirection: 'column', gap: '0.35rem' }}>
        <div style={{
          display: 'inline-flex',
          alignItems: 'center',
          gap: '0.4rem',
          fontSize: '0.725rem',
          fontFamily: 'monospace',
          color: '#60A5FA',
          fontWeight: 600,
          letterSpacing: '0.03em',
        }}>
          <span style={{
            width: '6px',
            height: '6px',
            borderRadius: '50%',
            background: '#60A5FA',
            boxShadow: '0 0 8px rgba(96, 165, 250, 0.6)',
          }} />
          <span>React 19 Island</span>
        </div>
        <p style={{ fontSize: '0.95rem', fontWeight: 600, color: '#FFFFFF', margin: 0 }}>{message}</p>
        <span style={{ fontSize: '0.75rem', color: '#8E8E98', fontFamily: 'monospace' }}>
          Passed Props: runtime={runtime} &bull; rendered={timestamp || 'just now'}
        </span>
      </div>

      <div style={{
        display: 'flex',
        alignItems: 'center',
        gap: '0.75rem',
        background: 'rgba(0, 0, 0, 0.4)',
        border: '1px solid rgba(255, 255, 255, 0.1)',
        padding: '0.35rem 0.65rem',
        borderRadius: '8px',
      }}>
        <button
          onClick={() => setCount((prev) => prev - 1)}
          style={{
            background: 'rgba(225, 29, 99, 0.15)',
            border: '1px solid rgba(225, 29, 99, 0.3)',
            color: '#FFB2BF',
            width: '28px',
            height: '28px',
            borderRadius: '6px',
            fontWeight: 700,
            fontSize: '1.1rem',
            cursor: 'pointer',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
          }}
          aria-label="Decrement counter"
        >
          -
        </button>
        <span style={{
          fontFamily: 'monospace',
          fontSize: '1.1rem',
          fontWeight: 700,
          color: '#FFFFFF',
          minWidth: '28px',
          textAlign: 'center',
        }}>
          {count}
        </span>
        <button
          onClick={() => setCount((prev) => prev + 1)}
          style={{
            background: 'rgba(225, 29, 99, 0.15)',
            border: '1px solid rgba(225, 29, 99, 0.3)',
            color: '#FFB2BF',
            width: '28px',
            height: '28px',
            borderRadius: '6px',
            fontWeight: 700,
            fontSize: '1.1rem',
            cursor: 'pointer',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
          }}
          aria-label="Increment counter"
        >
          +
        </button>
      </div>
    </div>
  );
}
