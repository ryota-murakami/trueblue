import autobind from 'autobind-decorator'
import React from 'react'
import Actions from '../actions/home'
import { connect } from 'react-redux'
import { Button } from 'react-bootstrap'
import { Modal } from 'react-bootstrap'
import { ListGroup } from 'react-bootstrap'
import Flatpickr from 'react-flatpickr'
import { isSP } from '../utils/util'

import '../../sass/component/material_green.scss'
import  '../../sass/component/modal.scss'

@autobind
export class Menu extends React.Component {

    constructor(props, context) {
        super(props, context)
        this.state = {
            showModal: false
        }
    }

    close() {
        this.setState({showModal: false})
    }

    open() {
        this.setState({showModal: true})
    }
    
    logout() {
        location.href = '/logout'
    }

    onClickSpecificDate(date) {
        this.close()
        this.props.moveToSpecificDate(date, this.props.timelineJson)
    }

    render() {
        const bsSize = isSP() ? undefined : 'large'
        return (
            <div id="menu">
                <Button className="menu-btn" bsSize={bsSize} onClick={this.open}>&#9776;</Button>

                <Modal show={this.state.showModal} onHide={this.close}>
                    <Modal.Header closeButton>
                        <Modal.Title>Menu</Modal.Title>
                    </Modal.Header>
                    <Modal.Body>
                        <ListGroup>
                            <Flatpickr onChange={this.onClickSpecificDate} options={{defaultDate: this.props.currentDate, inline: true, enable: this.props.timelineDateList}} />
                        </ListGroup>
                    </Modal.Body>
                    <Modal.Footer>
                        {this.props.isLogin ? <Button onClick={this.logout}>Logout</Button> : ''}
                    </Modal.Footer>
                </Modal>
            </div>
        )
    }
}

const mapStateToProps = (state) => (
    {
        timelineJson: state.homeState.timelineJson,
        isLogin: state.homeState.isLogin,
        currentDate: state.homeState.currentDate,
        timelineDateList: state.homeState.timelineDateList
    }
)

function mapDispatchToProps(dispatch) {
    return {
        moveToSpecificDate: (date, timelineJson) => {
            dispatch(Actions.moveToSpecificDate(date, timelineJson))
        }
    }
}

export default connect(mapStateToProps, mapDispatchToProps)(Menu)
