import React from 'react';
import { createRoot } from 'react-dom/client';
import './styles.css';
import App from './App.jsx';

const root = document.getElementById('omnigocrm-react') || document.getElementById('omnigocrm-app');
if (!root) throw new Error('OmniGoCRM mount element not found.');

createRoot(root).render(
  <React.StrictMode><App /></React.StrictMode>
);
