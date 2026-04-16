import React from 'react';
import { useWorkspace } from '../../context/WorkspaceContext.jsx';
import HomePage from './HomePage.jsx';
import PageView from '../views/PageView.jsx';
import BlogView from '../views/BlogView.jsx';
import CartView from '../views/CartView.jsx';
import OrdersView from '../views/OrdersView.jsx';
import ProductView from '../views/ProductView.jsx';
import CheckoutBlock from '../views/CheckoutBlock.jsx';

export default function PageRouter() {
  const { state, navigate } = useWorkspace();

  switch (state.activeView) {
    case 'page':     return <PageView />;
    case 'blog':     return <BlogView />;
    case 'cart':     return <CartView />;
    case 'orders':   return <OrdersView />;
    case 'product':  return <ProductView />;
    case 'checkout': return <CheckoutBlock onDone={() => navigate('home')} />;
    case 'products': return <HomePage showProductsOnly />;
    case 'home':
    default:         return <HomePage />;
  }
}
