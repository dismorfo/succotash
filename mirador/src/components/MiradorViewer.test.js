import { render, screen } from '@testing-library/react';
import MiradorViewer from './MiradorViewer';

test('renders learn react link', () => {
  render(<MiradorViewer />);
  const linkElement = screen.getByText(/learn react/i);
  expect(linkElement).toBeInTheDocument();
});
