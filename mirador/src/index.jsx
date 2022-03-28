import * as ReactDOM from 'react-dom'
import MiradorViewer from './components/MiradorViewer'
import './sass/style.scss'

const elm = document.getElementById('root')

ReactDOM.render(<MiradorViewer manifest={elm.dataset.manifest} />, elm)
