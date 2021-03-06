import autobind from 'autobind-decorator'
import React from 'react'
import { connect } from 'react-redux'
import Actions from '../../actions/home'
import { Button } from 'react-bootstrap'

@autobind
export class ImportButton extends React.Component {
    
    constructor(props, context) {
        super(props, context)
        this.state = {
            isLoading: false
        }
    }
    
    handleClick() {
        this.setState({isLoading: true})
        
        if (this.props.isInitialImportDebug) {
            return setTimeout(function() {
                console.log('debug initial import')
                this.props.debugImport()
            }.bind(this), 8000)
        }
        
        this.props.import()
    }
    
    renderLoadingText() {
        if (this.state.isLoading) {
            return (
                <div><i className="fa fa-spinner fa-spin"></i> {this.props.transText.import.btn.loading}</div>
            )
        }
        
        return (
            <div>{this.props.transText.import.btn.default}</div>
        )
    }
    
    render() {
        let isLoading = this.state.isLoading
        const loadingText = this.renderLoadingText()
        return (
            <Button
                bsStyle="primary"
                disabled={isLoading}
                onClick={!isLoading ? this.handleClick : null}
            >
                {loadingText}
            </Button>
        )
    }
}

const mapStateToProps = (state) => (
    {
        transText: state.homeState.transText,
        isInitialImportDebug: state.homeState.isInitialImportDebug
    }
)

function mapDispatchToProps(dispatch) {
    return {
        debugImport: () => {
            dispatch(Actions.debugImport())
        },
        import: () => {
            dispatch(Actions.import())
        }
    }
}

export default connect(mapStateToProps, mapDispatchToProps)(ImportButton)
