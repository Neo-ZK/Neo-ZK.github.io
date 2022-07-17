---
layout: post
title: 同为tls，quic-tls和标准tls到底有什么区别？
categories: Network
keywords: quic, tls
---

## 背景       
对于做网络的同学来说，quic和tls1.3肯定不是一个新名词了，quic从google版本进化到现在，其加密的协议也从google-crypto变成了tls1.3。然而，在quic协议的众多draft中，存在着一版关于quic-tls的draft，而且其内容还相当繁杂，那么说明quit-tls一定存在着某些点与我们现在熟知的tls over tcp不同的地方，那么：

- 这篇quic-draft到底在标准化什么呢？quic tls的模型到底和tls over tcp的模型有什么区别？
- 是什么造成了这样的区别？
- 如此的设计解决了什么样的问题，有什么问题没有解决，又造成了什么样的问题？

为了回答这几个问题，也就有了本文。当然，如果本文是在翻译draft，那也没什么特别的价值，而且draft内容特别多，一篇短文也写不完。我们知道，quic期望实现的是一个安全的(tls)，可靠传输的(基于udp设计的传输方案)，高效的(传输层面的0/1-rtt)，无队头阻塞的协议。所以本文也会围绕这几个点，以tls这部分作为核心，来分析一下quic在tls层面上关于这些点上面做了什么设计，以及背后的原因。

## 概念定义
我在拟定题目以及在引言中阐述文章的几个问题时感觉特别拗口，因为怎么表述都难以简洁且精确的表明我的想法，其根因还是在于quic本身是一个糅合了udp可靠传输+tls的协议，并且在分层上并不是特别清晰，所以我觉得有必要专门用一小节同步一些概念，并对文中的专有名词做一些定义。同时为方便大家后文的理解，这里对一些基本的概念也做一些简单的说明。

文中的名词定义：

- 后文中的quic都只表示quic协议中的udp+可靠传输这部分
- 后文中的quic-tls仅表示quic中tls这部分

同时需要同步两个基础概念：

- tls协议的目的主要在于五个大点：1)对称密钥协商 2) 身份认证 3) 安全传输 4)信息的不可篡改 5) 防重放攻击。所以无论怎么对tls协议进行修改，只要满足以上这些诉求，那么就是一个安全的协议，提这一点是为了让大家知道quic虽然与通用的tls over tcp模式很不一样，但都是围绕这些基础点做的设计，是可以保证安全可信的。
- quic以帧为基本数据单位，以quic包为基本传输单位(后文有时也会写作quic packet，用于和draft中的名词一致)，udp传输的基本单位为报文(datagram)，其对应关系为：一个udp报文中可以有多个quic包，一个quic包中可以有多个帧。而quic的流(stream)是一种针对帧的抽象(即quic包除了严格底层的packet number不存在有序性标示)

OK，开始我们的正文。

## Why not tls over quic
传统的tls over tcp的分层模式优势非常明显，协议层面的解耦对于排查问题，组件复用非常友好。既然quic基于udp实现了报文可靠传输的能力，为什么不用tls over quic这种方案呢？从开发者的角度来讲，至少tls这部分不用改啥东西了，相应的tls库拿来即用它不香吗？

如果你对quic协议本身有了解，那么你的第一反应也许是因为quic要实现整个传输层面实现0-rtt/1-rtt会话建联的能力不得已而为之。但其实这并不是核心问题，因为我们也同样也可以把clientHello塞到syn报文里面去实现0-rtt/1-rtt，那么问题到底在哪呢？

我们知道，在tls over tcp的模式下，tls可以保护tcp报文中payload的数据，但本身tcp的头部是会不会做任何加密的，TCP协议本身在设计时候并没有充分考虑其安全性，TCP的安全性依赖于其连接的，如果攻击者能够得知当前链接滑动窗口内有效的seq范围时，就可以做很多攻击。典型的攻击方式如"blind in-window attacks"，一旦伪造数据包的序列号落到了窗口上，攻击者就可以去注入恶意链接或者直接重置连接。目前tcp针对这类安全问题的解决手段主要还是通过增加链接的不可确定性，提升攻击难度。但这些方式都是指标不治本，攻击者在理论上仍旧存在攻击的可能性。

于quic而言，quic传输的基本单位是Packet，其中的packet number属于类似seq的存在，其受攻击面更广，因为packet number的设计是严格递增的，那么伪造一个可用的报文的可能性就会更大，那么如何去解决这个安全问题呢？那么答案是显而易见的，把packet number/seq拿去加密即可。这就是quic在安全设计上的最大考量之一，协议的不分层的根因也在于这点。

而当协议之前出现耦合时，quic和quic-tls就不得不做交互了，这也就是quic-tls的draft中核心想要标准化的部分之一：定义一个简洁而清晰的quic和quic tls的交互流程:

![](/images/self-drawn/diff-quic-tls/quic-tls-interaction.png)

如图所示，quic为tls提供可靠传输能力，tls为quic头部提供安全能力。然而tls的安全是需要一定的数据交互才可以实现的(需要通信双方知道相同的key才能加解密)，如果每个报文都既要可靠传输，又要保障安全，那么从逻辑上来讲，这种相互依赖关系就成了一个鸡生蛋的问题。因此，quic报文并不是所有包的Header都是完全安全的，我们来看看这部分的设计：quic以packet为单位定义了6种包加密方式:

![](/images/self-drawn/diff-quic-tls/pkt-enc.png)

先对这些Packet做一些简单的说明：其中的Retry packet本身不加密，这里定义的key是用于加密retry packet中的integrity的，这里就不展开讲了。而Version Negotiation Packet则是完全明文的。我们这里主要关注另外四种包加密方式。如果你对本身的quic-transport-draft足够熟悉的话，你会发现quic在数据传输层面仅定义了两种Packet：Long Header Packet(用于进行tls协商)，Short Header Packet(tls协商成功后，传输应用层数据)，那么这里的Initial Packet，0-rtt Packet， Handshake Packet，都是属于Long Header Packet。目前报文Header中都是对Packet Number及其length进行加密，short Header Packet在此基础之上还会对key phase字段加密(用于key update的，这里不展开)。

回到咱们的交互流程及安全性的问题上来，我们现在知道了Init，0-rtt，Handshake包是用于tls交互的，看起来三个类型的Packet都是要加密的，那鸡生蛋问题咋解决的呢？这里就要详细介绍下Init包，Init对应的Initial secret实质是一个固定的值，在draft中的定义如下：

![](/images/self-drawn/diff-quic-tls/quic-key.png)

也就是说，init secret其实基本就在明文的状态跑了(因为client_dst_connection_id就是明文)，那这部分的加密还有什么意义吗？从现实层面的角度来说，这部分功能保证了数据不直接暴露给中间人，增加了一定的攻击成本，算是比较有用吧。OK，当有了不安全的Init Packet后，整个交互的流程就是可行的了，draft定义的tls交互流程的状态图(包括和quic-tls和quic交互的部分)如下：

![](/images/self-drawn/diff-quic-tls/quic-tls.png)

这部分和tls1.3的交互流程是大体一致的。不同点在于，quic-tls把tls的状态变化体现在了发送的包的类型上面，而tls over tcp的状态变化，则是体现在了链接层面。

## 队头阻塞问题
如果你对quic有一些了解，那么你肯定知道quic的最大优势之一就在于解决了传输层面的队头阻塞问题。但是，quic真的完美解决了队头阻塞问题吗？这里先不给出答案，我们来看看quic是怎么解决队头阻塞问题的。

不同于tcp，quic通过严格递增的Packet Number保证包的有效性，并通过对消息分帧，加入stream的抽象，即相同stream下的数据帧通过stream id来标示，并通过offset字段来描述其顺序性。那么，这也就做到了在可靠传输的层面上，任意一个Packet丢失都不会导致后面的Packet发送阻塞。

但是，当quic将quic-tls糅进来之后，事情就变得麻烦了，我们知道，tls是一个有状态的协议，在状态成功完成跳转之前，下一个状态的数据是不可以发送的，举个最简单的例子：client在发送Init Packet时，是没法发送1-rtt packet的，因为都还没有生成packet加密的key嘛。所以在quic的tls链接建立阶段，是一定会存在队头阻塞的问题的。那么在quic-tls完成后，数据的传输就不会有队头阻塞了吗？也不是，因为只要涉及到状态变化就有可能会导致队头阻塞，而quic-tls舍弃了tls1.3的key update机制，自己搞了个全新的tls握手成功后的key update机制，这个机制就是更新一次会话过程中的对称密钥，那么在发生key update时也会队头阻塞，因为需要确认双方均接收密钥更新才能继续往下走。

那么，针对这些队头阻塞问题，quic有做什么优化吗？答案是有，但是也不太多，因为状态转移从原理上来说就是必须阻塞等待到进入下一阶段的。所以优化的场景都是围绕着通信一端在某一时刻，同时维护了多个需要发送信息的状态的情况。这句话说着有点拗口，举个简单的例子：在client将发送client finish状态的时候，当前其实已经拥有了handshake的状态和1-rtt的状态，因此它可以同时发送client finish和1-rtt数据给server。熟悉tls协议同学可能会觉得这是一个很显然的做法，因为我们一直在说的tls1.3的1-rtt就是这么实现的，包括tls1.3的rfc里的交互图，也是1-rtt数据和client finish一起发送的。

但quic-tls和tls over tcp不同之处在于：tls over tcp的交互完全依赖tcp来保证数据的有序性，但quic-tls和quic可靠传输是耦合在一起的，quic可靠传输依赖stream抽象来保证有序性，但加密的单位却是packet，即packet未解密前我们根本无法判定数据的有序性。带来的效果是，如果不同加密等级的quic包发生了乱序，接收端的行为只能由quic实现者根据具体情况来确定实现的策略。这一大段话怎么理解呢？我们举个例子：

client向server同时发送了1-rtt数据和client finish数据，此时网络发生了乱序，1-rtt的包先到了，由于server没有client finish的消息，无法生成1-rtt的key，也就无法解密1-rtt的报文，同时也就无法确定1-rtt数据里面的offset(即报文的顺序性)，那么此时server的方案只有两种，要么把所有1-rtt数据都缓存下来，要么就把1-rtt数据进行丢弃。两种解决方案都有各自的问题。对于缓存的方案：在client finish成功解析前，所有1-rtt数据都需要缓存，那么恶意攻击者可能利用这个方案直接把server的内存打爆。对于直接丢弃的方案：对于client来说，没有收到client finish的ack，也就无法确定server成功解析了1-rtt的key，因此也就只能阻塞等待，直到收到了client finish的ack，才能继续发送1-rtt数据，那么整个握手其实变成了2-rtt，对于1-rtt数据而言，又形成了队头阻塞问题。

两个解决方案实质上是一个trade-off，但quic由于packet number和帧的offset都是加密的，因此也没有办法去实现类似tcp的滑动窗口的方案，所以，这部分只能留给quic的实现者自己决定，不过quic-tls也给出了一些技巧性建议，比如可以通过quic的coalesced机制(即多个quic packet放在一个udp报文里面发送)，在收到client finish的ack之前，每个1-rtt的包都加一个client finish的冗余包。但具体怎么实现，还是只能看实现者根据具体的情况来设定。

## 总结
其实quic-tls和本身tls由于头部设计等问题，也还存在着很多细微的差别，比如套件的限制等，但我觉得这都不是特别关键，而且由于篇幅问题，要在这一篇文章里说完也不大现实。有兴趣的同学可以私聊我看看部分总结文档，或者直接移步quic-tls的draft看更具体的说明，这里就对文章提出的几个问题做一些简单的总结吧：

quic-tls的draft在标准化什么：
1. quic和quic-tls的分层模型，quic-tls通信双方的传输流程，quic-tls通信的基本单位及其加密方式
2. quic和quic-tls的交互流程
3. 由于报文设计及分层变化，带来的一些tls层面的细节变化说明

quic及quic-tls的模型和tls over tcp的区别：
1. quic和quic-tls不分层，紧耦合，两者通过draft中定义的标准交互流程来进行交互。
2. quic-tls加密的单位不再是tls报文，而是quic packet。tls的状态变换会直接反映到quic pakcet的encrypt level上，换句话说就是quic的tls状态是由quic packet的encrypt level反映的

造成区别的原因：
1. quic以packet为基本传输单位，由于packet number的自增性，有效packet太容易被伪造，因此会需要加密，因此发生了紧耦合

解决的问题：
1. quic packet的安全性
2. quic packet在tls交互层面也可以在某种层度上解决队头阻塞问题

未解决的问题：
1. 在tls层面的队头阻塞问题仍旧大量存在
2. 实现层面如何根据具体的情况实现相关解决方案

带来的新的问题：
1. key update引入了新的队头阻塞问题。