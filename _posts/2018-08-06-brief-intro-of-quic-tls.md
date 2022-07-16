---
layout: post
title: QUIC协议---QUIC中的TLS
categories: Blog
keywords: QUIC, Network
---

## 背景       
QUIC已经不是一个新名词了，它在2012年由google提出，到现在已经是draft13了，在chrome上也早已应用了quic协议，那我们今天就来看下QUIC中的安全通信和传统的安全通信有什么异同。


## QUIC中安全层的位置
说到安全通信，那么肯定不得不提tls（传输安全层），目前tls1.3的draft也已经更新到版本28，离正式RFC也没多远了，QUIC的安全通信流程本质上是和tls1.3是一样的，但QUIC的安全协议架构呢又和传统tls所处的层面有一点区别，下面我们就来对比下传统架构，看看QUIC有什么不同：
![](/images/self-drawn/brief-intro-of-quic-tls/quic-tls-stack.png)  

可以看到的是，QUIC几乎囊括了传输层，安全层以及应用层的大部分功能，在传输层上，QUIC基于UDP协议保证了数据包的可靠传输，比如实现了相比TCP更加优秀的拥塞控制，更丰富的ACK策略，更精确的RTT计算，解决了TCP重传歧义问题等，在安全层上实现了和TLS一样的能力，在应用层上复用了HTTP2的多路复用功能，而且由于是基于UDP协议实现的，还从根本上解决了HTTP2的队头阻塞问题（HTTP2相对于HTTP1.1只是在应用层解决了队头阻塞问题，当在传输层是还是公用了一个TCP连接，当TCP头包发生阻塞时，队头问题依然存在）。

今天我们就来聊一聊QUIC中的安全层，QUIC中严格来说不能把安全层分为一个层，我们想象中的QUIC协议内部也许是这样的：
![](/images/self-drawn/brief-intro-of-quic-tls/quic-tls-imagine.png)

可它实际上是这样的
![](/images/self-drawn/brief-intro-of-quic-tls/quic-tls-real.png)

什么意思呢？我们知道，整个OSI的7层模型中，协议从上至下是层层封装的，每层之间互不干扰，而在QUIC中，整个安全功能没有单独抽离出来，而是揉成了一整个协议栈，我们来看看TLS相关字段在QUIC帧中的位置。
![](/images/self-drawn/brief-intro-of-quic-tls/quic-tls-rfc.png)

好像乍一看还是分了层的，实则不然，整个QUIC帧头部携带了TLS相关的字段，用于向通信对方表明握手状态等相关信息，而TLS中如record层（record层的功能就是保证消息的AEAD（加密即认证））等相关功能又直接融入到QUIC的包体里面去了，总的来说呢，QUIC数据包的安全认证的过程就是通过TLS提供的API实现AEAD认证，安全握手等，然后在TLS Handshake和TLS Alerts字段来表明此次安全通信的情况，而tls依赖QUIC保证整个数据包传输的完整性和正确性，安全层和传输层不再有明确的分层情况。

这样做有啥好处呢？我们知道，TCP协议和UDP协议本身是没做任何安全处理的，在这一层数据遭到篡改是没法识别的，但QUIC可以说是武装到了牙齿，除了个别报文，大部分报文头部都是需要认证的，也就是说，只要对QUIC报文做了篡改，接收端都很容易发现。

虽然QUIC没有将安全层区分的很开，但为了方便理解，后面我们还是将QUIC中的安全部分单独拎出来分析，后面我们说QUIC协议就是指QUIC中的流量控制等传输层协议，TLS就是指QUIC中的安全协议。

## QUIC中的安全握手流程
QUIC的安全握手本质上就是TLS标准的安全握手，如果你熟悉TLS1.2和TLS1.3的标准，那么QUIC的安全握手你也就很清晰了，和传统TCP+TLS握手稍有区别的是，传统模式下，需要在TCP3次握手后再进行TLS握手，而QUIC由于是基于UDP的通信协议，在第一个数据包中就可以携带TLS握手的相关信息，为此换来了同等情况下的1个RTT的性能提升。示例图如下：
![](/images/self-drawn/brief-intro-of-quic-tls/quic-tls-intereaction.png)

## 整体协议中QUIC和TLS的交互流程
QUIC在安全层中区分了4个加密等级，这个分级对应的是TLS1.3中的几种client和server的交互方式，这四个等级如下：
- 明文通信
- 用Early_data的key加密的消息（主要用于0-RTT功能）
- 用Handshake key加密的消息
- 用最终协商得到的应用层对称密钥加密的消息。

而与TLS1.3不同的是，QUIC中存在很多类型的帧，并且严格限制这些帧只能在某个或某些等级下使用，比如：
- Crypto帧可以出现在任意4个加密等级中
- CONNECTION_CLOSE 帧不能出现在level 2中，其余都可以
- PADDING 帧和PING帧可以出现在任意4个加密等级中
- ACK帧不能出现在level 2中，其余都可以，但需要注意的是，ACK帧的安全等级必须和它所想要确认的帧的安全等级是一样的才行。
- STREAM 帧只能出现在level 2和4中
- 其余所有帧只能出现在level 4中。

区分安全等级主要是适配QUIC协议的流量控制，任何时刻下，QUIC中的TLS层都会存在一个当前的发送加密等级和接受加密等级，每个加密级别和一个数据流相关联，数据流通过加密帧发送到通信的另一方去。当需要发送TLS层生成的握手消息时，消息会被附加到当前的flow中去，然后被包含到一个数据包的CRYPTO帧中，而CRYPTO帧则要求是被当前加密等级下的key加密的。

在开始QUIC的握手前，QUIC会提供一个quic_transport_parameters给TLS层，然后通过TLS的扩展来进行发送，这个字段用于说明QUIC的版本号，一般这个会在TLS中的ClientHello或EncryptedExtension类型消息中发送。而当通信一方收到一个包含CRYPTO帧的QUIC报文时，处理流程如下：
![](/images/self-drawn/brief-intro-of-quic-tls/quic-tls-process.png)